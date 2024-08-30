from re import I
from transformers import AutoModelForSeq2SeqLM, AutoTokenizer, PreTrainedTokenizer, PreTrainedModel
import math
import torch
import textwrap as tw

from FileHandler import FileHandler
from KnowledgeGraph import KnowledgeGraph


class KnowledgeBase:
    def __init__(self, tokenizer: PreTrainedTokenizer, model: PreTrainedModel, gpu=False, knowledgeGraph: KnowledgeGraph=None):
        self.gpu = gpu
        self.tokenizer = tokenizer
        self.model = model
        
        if knowledgeGraph is None: 
            self.graph = KnowledgeGraph()
        else:
            self.graph = knowledgeGraph
        
        if self.gpu: self.model.to('cuda')
        

    def AddGraph(self, graphData: str, verbose=False):
        newGraph = KnowledgeGraph()
        newGraph.LoadData(graphData, verbose)
        self.graph.AddGraph(newGraph, verbose, priorityToNegativeLevel=False)
        
    
    def AddRelation(self, head, relType, tail):
        self.graph.AddRelation(head, relType, tail)


    def AddTextToKB(self, text, spanLen=128, maxTokenizerLen=1024, wrap=True, verbose=False):    
        relationList = self.TextToRelations(text, spanLen, maxTokenizerLen, wrap, verbose=True)
        
        if verbose: print(f'\nNumber of relations: {len(relationList)}')

        for relation in relationList:
            self.AddRelation(relation['head'], relation['type'], relation['tail'])
    
        if verbose: self.graph.PrintRelations
        if verbose: self.graph.PrintEntities
            
        return


    def ChangeEntityLevelsFromText(self, text, amountToAdd, spanLen=128, maxTokenizerLen=1024, wrap=True, verbose=False, normalized=False):
        relationList = self.TextToRelations(text, spanLen, maxTokenizerLen, wrap, verbose)
        entitiesAltered = []
        
        for relation in relationList:
            headEntity = relation['head']
            tailEntity = relation['tail']
            
            if verbose: print(f'Entity1 = {headEntity}; Entity2 = {tailEntity}')
            
            if not normalized or headEntity not in entitiesAltered: 
                self.graph.ChangeEntityLevel(headEntity, amountToAdd)
                entitiesAltered.append(headEntity)
            
            if not normalized or tailEntity not in entitiesAltered: 
                self.graph.ChangeEntityLevel(tailEntity, amountToAdd)
                entitiesAltered.append(tailEntity)


    def ConvertTextChunk(self, text, spanLen=128, maxTokenizerLen=512, verbose=False):
        # Tokenize
        genKwargs = {
            "max_length": 64,
            "length_penalty": 0,
            "num_beams": 3,
            "num_return_sequences": 3
        }
    
        modelInput = self.tokenizer(text, max_length=maxTokenizerLen, padding=True, truncation=True, return_tensors='pt')
        noOfTokens = len(modelInput['input_ids'][0])
        if verbose: print(f"Num tokens = {noOfTokens}")

        # Calculate Spans
        noOfSpans = math.ceil(noOfTokens / spanLen)
        if verbose: print(f'Dividing into {noOfSpans} spans...')

        # Calculate Spans Boundaries
        overlap = math.ceil((noOfSpans * spanLen - noOfTokens) / max(noOfSpans - 1, 1))
        spansBoundaries = []
    
        start = 0
        for i in range(noOfSpans):
            spansBoundaries.append([start + spanLen * i, start + spanLen * (i + 1)])
            start -= overlap
    
        if verbose: print(f"Span boundaries are {spansBoundaries}")
        if verbose: print(f'wholeInput = {modelInput}\n')

        # Divide modelInput with Spans Boundaries
        input_ids = []
        attention_mask = []

        for bound in spansBoundaries:
            input_ids.append(modelInput['input_ids'][0][bound[0]:bound[1]])
            attention_mask.append(modelInput['attention_mask'][0][bound[0]:bound[1]])

        if self.gpu:
            modelInputList = {'input_ids': torch.stack(input_ids).cuda(), 
            'attention_mask': torch.stack(attention_mask).cuda()}       
        else:
            modelInputList = {'input_ids': torch.stack(input_ids), 
            'attention_mask': torch.stack(attention_mask)}    

        if verbose: print(f'dividedInput = {modelInputList}\n')
    
        # Feed all parts to model
        modelOutput = self.model.generate(**modelInputList, **genKwargs)
        decodedOutput = self.tokenizer.batch_decode(modelOutput, skip_special_tokens=False)
    
        if verbose: print(f'decodedOutput = {decodedOutput}')
    
        # Interpret and feed to KB
        relationList = []

        for outputIndex in range(len(decodedOutput)):
            spanRelationList = self.InterpretTriplets(decodedOutput[outputIndex])
            relationList += spanRelationList
        
        return relationList


    def GetNoEntities(self):
        return self.graph.GetNoOfEntities()


    def GetNoRelations(self):
        return self.graph.GetNoOfRelations()


    def GetKBData(self, verbose=False):
        return self.graph.GetData(verbose)


    def InterpretTriplets(self, text):
        relations = []
        relation, subject, relation, object_ = '', '', '', ''
    
        text = text.strip()
        current = 'x'
        text_replaced = text.replace("<s>", "").replace("<pad>", "").replace("</s>", "")
    
        for token in text_replaced.split():
            if token == "<triplet>":
                current = 't'
                if relation != '':
                    relations.append({
                        'head': subject.strip(),
                        'type': relation.strip(),
                        'tail': object_.strip()
                    })
                    relation = ''
                subject = ''
            elif token == "<subj>":
                current = 's'
                if relation != '':
                    relations.append({
                        'head': subject.strip(),
                        'type': relation.strip(),
                        'tail': object_.strip()
                    })
                object_ = ''
            elif token == "<obj>":
                current = 'o'
                relation = ''
            else:
                if current == 't':
                    subject += ' ' + token
                elif current == 's':
                    object_ += ' ' + token
                elif current == 'o':
                    relation += ' ' + token

        if subject != '' and relation != '' and object_ != '':
            relations.append({
                'head': subject.strip(),
                'type': relation.strip(),
                'tail': object_.strip()
            })

        return relations


    def IsRelationEqual(self, rel1, rel2):
        return all(rel1[attr] == rel2[attr] for attr in ['head', 'type', 'tail'])


    def LoadKBData(self, data, verbose=False):
        self.graph.LoadData(data,verbose)

    
    def PruneGraph(self, minValue=2, verbose=False):
        self.graph.PruneGraph(minValue,verbose)
    
    
    def RelationsToEntities(self, relations, verbose=False):
        entities = []
        
        if verbose: print('\nConverting relations to entities...')
        
        for relation in relations:
            head = relation['head']
            tail = relation['tail']
            
            if not head in entities: entities.append(head)
            if not tail in entities: entities.append(tail)
    
        if verbose: print(f'Entities extracted: {entities}\n')
        return entities
    
    
    def ResetAllLevels(self):
        self.graph.ResetAllLevels()


    def TextToRelations(self, text, spanLen=128, maxTokenizerLen=512, wrap=True, verbose=False):
        if wrap:
            wrapper = tw.TextWrapper(width=maxTokenizerLen)
            wrappedText = wrapper.wrap(text)
            relations = []
        
            if verbose:
                print(f'Original text length: {len(text)}, number of chunks = {len(wrappedText)}, chunk length = {maxTokenizerLen}')

            for index, chunk in enumerate(wrappedText):
                if verbose: print(f'Processing chunk {index+1}/{len(wrappedText)}...')
                relation = self.ConvertTextChunk(chunk, spanLen, maxTokenizerLen, verbose=False)
                relations.extend(relation)
                
        else:
            if verbose: print(f'Converting in normal mode. Text length: {len(text)}...')
            relations = self.ConvertTextChunk(text, spanLen, maxTokenizerLen, verbose=False)
            
        if verbose: print(relations)
        return relations


    def Visualize(self, filename='index.html'):
        return self.graph.Visualize(filename)
    
    
    def VisualizeEntityRelations(self, entity, filename='KnowledgeSubGraph.html', numberOfLevels=0, verbose=False):
        self.graph.VisualizeEntityRelations(entity,filename,numberOfLevels,verbose)
    

    def DEBUG_PrintRelations(self):
        self.graph.DEBUG_PrintRelations()
    
    
    def DEBUG_PrintEntities(self):
        self.graph.DEBUG_PrintEntities()