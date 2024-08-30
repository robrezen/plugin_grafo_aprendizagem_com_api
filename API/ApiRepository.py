from fastapi.responses import HTMLResponse
from wikipedia import wikipedia
from scipy.interpolate import interp1d
from KnowledgeBase import KnowledgeBase
from KnowledgeGraph import KnowledgeGraph
from Models import DetachGraphRequest, GraphHtmlRequest, GraphPruneRequest, MergeGraphsRequest, NewGraphRequest, UpdateGraphRequest, UpdateLevelRequest, UpdateLevelsRequest
from transformers import AutoModelForSeq2SeqLM, AutoTokenizer
from MoodleWebhookCaller import MoodleWebhookCaller

WEBSERVICE_URL = 'http://localhost/webservice/rest/server.php?'
WSTOKEN = 'wstoken=d72c135eff1c1eccebd1235249e06078'
WSFUNCTION = 'mod_learninggraph_ws_data_received'
MOODLEWSRESTFORMAT = 'moodlewsrestformat=json'

caller = MoodleWebhookCaller(WEBSERVICE_URL, WSTOKEN, WSFUNCTION, MOODLEWSRESTFORMAT)

class ApiRepository:
    
    def GetNewGraph(newGraphRequest: NewGraphRequest):
        model = AutoModelForSeq2SeqLM.from_pretrained("Babelscape/rebel-large")
        tokenizer = AutoTokenizer.from_pretrained("Babelscape/rebel-large")
        KB = KnowledgeBase(tokenizer, model, gpu=False)
    
        text = newGraphRequest.text
    
        KB.AddTextToKB(text, verbose=True)
        graphData = KB.GetKBData(verbose=True)
    
        caller.call_moodle_webhook(newGraphRequest.cm_id, {"graph": graphData}, newGraphRequest.destination_type, questionid=newGraphRequest.id)
        print(f'\nEnded task. Result = {graphData}')
    

    def GetNewGraphExpanded(newGraphRequest: NewGraphRequest):
        model = AutoModelForSeq2SeqLM.from_pretrained("Babelscape/rebel-large")
        tokenizer = AutoTokenizer.from_pretrained("Babelscape/rebel-large")
        KB = KnowledgeBase(tokenizer, model, gpu=False)
    
        text = newGraphRequest.text
        textEntities = KB.RelationsToEntities(KB.TextToRelations(text), verbose=True)
    
        wikipedia.set_lang('pt')
        for entity in textEntities:
        
            try:
                page = wikipedia.page(entity)
                print(f'Adding page "{entity}"...')
        
            except: 
                print('Page not found! Skipping...')
                continue
        
            else: 
                pageText = page.content
                KB.AddTextToKB(pageText,verbose=True)
        
    
        KB.AddTextToKB(text, verbose=True)
        KB.PruneGraph(minValue=2, verbose=True)
        graphData = KB.GetKBData(verbose=True)
    
        caller.call_moodle_webhook(newGraphRequest.cm_id, {"graph": graphData}, newGraphRequest.destination_type)
        print(f'\nEnded task. Result = {graphData}')
    

    def GetGraphHtml(graphHtmlRequest: GraphHtmlRequest):
        graphData = graphHtmlRequest.graph
    
        graph = KnowledgeGraph()
        graph.LoadData(graphData, verbose=True)
        htmlData = graph.Visualize(verbose=True)
    
        caller.call_moodle_webhook(graphHtmlRequest.cm_id, HTMLResponse(content=htmlData, status_code=200), graphHtmlRequest.destination_type)
        print(f'\nEnded task. Result = {HTMLResponse(content=htmlData, status_code=200)}')
    

    def PruneGraph(graphPruneRequest: GraphPruneRequest):
        graphData = graphPruneRequest.graph
        minLevel = graphPruneRequest.minLevel
    
        graph = KnowledgeGraph()
        graph.LoadData(graphData, verbose=True)
        graph.PruneGraph(minLevel, verbose=True)
    
        newGraph = graph.GetData(verbose=True)

        caller.call_moodle_webhook(graphPruneRequest.cm_id, {"graph": newGraph}, graphPruneRequest.destination_type)
        print(f'\nEnded task. Result = {newGraph}')
    

    def UpdateGraph(updateGraphRequest: UpdateGraphRequest):
        model = AutoModelForSeq2SeqLM.from_pretrained("Babelscape/rebel-large")
        tokenizer = AutoTokenizer.from_pretrained("Babelscape/rebel-large")
    
        graphData = updateGraphRequest.graph
        textToAdd = updateGraphRequest.text
    
        graph = KnowledgeGraph()
        graph.LoadData(graphData, verbose=True)
        KB = KnowledgeBase(tokenizer, model, gpu=False, knowledgeGraph=graph)
    
        KB.AddTextToKB(textToAdd, verbose=True)
        newGraphData = KB.GetKBData(verbose=True)
    
        caller.call_moodle_webhook(updateGraphRequest.cm_id, {"graph": newGraphData}, updateGraphRequest.destination_type)
        print(f'\nEnded task. Result = {newGraphData}')
    

    def UpdateGraphLevel(updateLevelRequest: UpdateLevelRequest):
        model = AutoModelForSeq2SeqLM.from_pretrained("Babelscape/rebel-large")
        tokenizer = AutoTokenizer.from_pretrained("Babelscape/rebel-large")
    
        text = updateLevelRequest.text
        amount = updateLevelRequest.amount
        graphData = updateLevelRequest.graph
    
        graph = KnowledgeGraph()
        graph.LoadData(graphData, verbose=True)
        KB = KnowledgeBase(tokenizer, model, gpu=False, knowledgeGraph=graph)
    
        KB.ChangeEntityLevelsFromText(text, amount, verbose=True)
        newGraphData = KB.GetKBData(verbose=True)
    
        caller.call_moodle_webhook(updateLevelRequest.cm_id, {"graph": newGraphData}, updateLevelRequest.destination_type)
        print(f'\nEnded task. Result = {newGraphData}')
        

    def MergeGraphs(mergeGraphsRequest: MergeGraphsRequest):
        print('\nMerging graphs...\n')
        
        graphBase = KnowledgeGraph()
        graphBase.LoadData(mergeGraphsRequest.graphBase, verbose=True)

        for graphToAdd in mergeGraphsRequest.graphsToMerge:
            newGraph = KnowledgeGraph()
            newGraph.LoadData(graphToAdd, verbose=True)
            graphBase.AddGraph(newGraph, verbose=True, priorityToNegativeLevel=mergeGraphsRequest.priorityToNegativeLevel)
        
        newGraphData = graphBase.GetData(verbose=True)
    
        caller.call_moodle_webhook(mergeGraphsRequest.cm_id, {"graph": newGraphData}, mergeGraphsRequest.destination_type)
        print(f'\nEnded task. Result = {newGraphData}')
        
    
    def DetachGraph(detachGraphRequest: DetachGraphRequest):
        graphBase = KnowledgeGraph()
        toDetach = KnowledgeGraph()
        graphBase.LoadData(detachGraphRequest.graphBase)
        toDetach.LoadData(detachGraphRequest.graphToDetach)

        graphBase.DetachGraph(toDetach, verbose=True)
        
        newGraphData = graphBase.GetData(verbose=True)
        
        caller.call_moodle_webhook(detachGraphRequest.cm_id, {"graph": newGraphData}, detachGraphRequest.destination_type)
        print(f'\nEnded task. Result = {newGraphData}')
        

    def UpdateLevels(updateLevelsRequest: UpdateLevelsRequest):
        model = AutoModelForSeq2SeqLM.from_pretrained("Babelscape/rebel-large")
        tokenizer = AutoTokenizer.from_pretrained("Babelscape/rebel-large")
        
        graphBase = KnowledgeGraph()
        graphBase.LoadData(updateLevelsRequest.graphBase)
        
        KB = KnowledgeBase(tokenizer, model, gpu=False, knowledgeGraph=graphBase)
        
        for i, question in enumerate(updateLevelsRequest.question_details):
            print(f'Changing levels for question {i}...')
            
            KB.AddGraph(question['graph'], verbose=True)
            
            questionGrade = question['grade']
            levelToAdd = int(interp1d([0,10],[-50,50])(questionGrade))
            KB.ChangeEntityLevelsFromText(question['questiontext'], levelToAdd, verbose=True)
            print(f'Question has grade {questionGrade} so added {levelToAdd} levels')
    
        newGraphData = KB.GetKBData(verbose=True)
            
        caller.call_moodle_webhook(updateLevelsRequest.cm_id, {"graph": newGraphData}, updateLevelsRequest.destination_type, userid=updateLevelsRequest.user_id)
        print(f'\nEnded task. Result = {newGraphData}')