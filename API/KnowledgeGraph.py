from pyvis.network import Network
from pyvis.options import Options, Configure
import jsonpickle as jspickle

class KnowledgeGraph:
    def __init__(self):        
        self.relations = []
        self.entities = {}
        jspickle.set_preferred_backend('json')
        jspickle.set_encoder_options('json', ensure_ascii=False)
    

    def AddGraph(self, graph: 'KnowledgeGraph', verbose=False, priorityToNegativeLevel=False):
        if verbose: print('\nAdding graph...')
        
        # Consolidate levels before merge
        self.ConsolidateGraphLevels(graph, verbose, priorityToNegativeLevel)
        
        for relation in graph.relations:
            self.AddRelation(relation[0]['head'], relation[0]['type'], relation[0]['tail'])
        
        return
    
        
    def AddRelation(self, head, relType, tail):
        rel = {'head': head, 'type': relType, 'tail': tail}

        # Add entity to entities list
        if head in self.entities.keys():
            self.entities[head]['importance'] += 1
        else:
            self.entities[head] = {'importance': 1, 'level': -1}
            
        if tail in self.entities.keys():
            self.entities[tail]['importance'] += 1
        else:
            self.entities[tail] = {'importance': 1, 'level': -1}
        
        # Add relation to relations list
        relIndex = self.FindRelation(rel)
        if relIndex == -1:
            self.relations.append([rel,1])
        else:
            self.relations[relIndex][1] += 1    
        
        return


    def ChangeEntityLevel(self, entityName, amountToSum):
        if not entityName in self.entities.keys():
            return -1
            
        if self.entities[entityName]['level'] == -1:
            self.entities[entityName]['level'] = 255 + amountToSum
            
        else:
            self.entities[entityName]['level'] += amountToSum
            
            
        total = self.entities[entityName]['level']
            
        if total > 510:
            print('Max Level reached!')
            self.entities[entityName]['level'] = 510
            return -1
            
        elif total < 0:
            print('Min level reached!')
            self.entities[entityName]['level'] = 0
            return -1
            
        return 0


    def ConsolidateGraphLevels(self, graphToAdd: 'KnowledgeGraph', verbose=False, priorityToNegativeLevel=False):
        if verbose: print('Consolidating levels before merge...')
        
        for entity in graphToAdd.entities.keys():
            if entity in self.entities:
                baseGraphLevel = self.entities[entity]['level']
                foreignGraphLevel = graphToAdd.entities[entity]['level']
                
                if baseGraphLevel >= 0 and foreignGraphLevel >= 0:
                    levelMean = int((baseGraphLevel + foreignGraphLevel) / 2)
                elif baseGraphLevel >= 0:
                    if priorityToNegativeLevel: levelMean = foreignGraphLevel
                    else: levelMean = baseGraphLevel
                elif foreignGraphLevel >= 0:
                    if priorityToNegativeLevel: levelMean = baseGraphLevel
                    else: levelMean = foreignGraphLevel
                else:
                    levelMean = -1
                    
                self.entities[entity]['level'] = levelMean                

                if verbose: print(f'\tFor entity {entity}: BaseGraphLevel = {baseGraphLevel}; ForeignGraphLevel = {foreignGraphLevel}; LevelMean = {levelMean}')
        
        return


    def DetachGraph(self, graphToDetach: 'KnowledgeGraph', verbose=False):
        if verbose: print('\nDetaching graph...\n')
        
        for entity in graphToDetach.entities.keys():
            if verbose: print(f'Checking entity {entity}...')
            
            if entity in self.entities:
                self.entities[entity]['importance'] -= graphToDetach.entities[entity]['importance']
                
                if self.entities[entity]['importance'] < 1: 
                    if verbose: print(f'\tImportance less than zero. Removing entity {entity}...')
                    self.RemoveEntity(entity, verbose=True)
        
        return
    
    
    def FindRelation(self, rel):
        for i in range(len(self.relations)):
            if self.relations[i][0] == rel: return i
            
        return -1


    def GetData(self, verbose=False):
        if verbose: print("\nRetrieving data...\n")
        return jspickle.dumps([self.entities,self.relations])
            

    def GetEntityRelations(self, entityToFind, numberOfLevels=0):
        relationList = []
        entityDict = {}
        
        if numberOfLevels == 0:
        
            for relation in self.relations:
                if relation[0]['head'] == entityToFind:
                    relationList.append(relation)
                    secondaryEntity = relation[0]['tail']
                    entityDict[secondaryEntity] = self.entities[secondaryEntity]

                elif relation[0]['tail'] == entityToFind:
                    relationList.append(relation)
                    secondaryEntity = relation[0]['head']
                    entityDict[secondaryEntity] = self.entities[secondaryEntity]
                
            entityDict[entityToFind] = self.entities[entityToFind]    
        
        else:
            
            entityRelationList = self.GetEntityRelations(entityToFind,numberOfLevels=0)
            entityDict = entityRelationList[1]
            relationList = entityRelationList[0]
            
            newEntities = {}
            for entity in entityDict:
                entityRelationList = self.GetEntityRelations(entity,numberOfLevels=numberOfLevels-1)
                newEntities.update(entityRelationList[1])
                relationList.extend(entityRelationList[0])
                
            entityDict.update(newEntities)
                
        return [relationList,entityDict]


    def GetNoOfEntities(self):
        return len(self.entities)


    def GetNoOfRelations(self):
        return len(self.relations)


    def LoadData(self, data, verbose=False):
        if verbose: print("\nLoading data...")
        
        decodedContent = jspickle.loads(data)
        
        if verbose:
            print(f'\tEntities loaded: {decodedContent[0]}')
            print(f'\tRelations loaded: {decodedContent[1]}')
            
        self.entities = decodedContent[0]
        self.relations = decodedContent[1]


    def PrintEntities(self):
        print('\n\n**********ENTITY LIST**********\n\n')
        
        for entity in self.entities:
            print(entity + ' = ' + str(self.entities[entity]))


    def PrintRelations(self):
        print('\n\n**********RELATION LIST**********\n\n')
        
        for relation in self.relations:
            print(relation)


    def PruneGraph(self, minValue=2, verbose=False):
        startingEntityNo = len(list(self.entities.keys()))
        startingRelationNo = len(self.relations)
        
        if verbose: print('\nPruning the graph...')
        entityKeyList = list(self.entities.keys())
        
        for i in range (0,len(entityKeyList)):
            key = entityKeyList[i]
            
            if self.entities[key]['importance'] < minValue:
                self.RemoveEntity(key)
                
        if verbose: print('\nPruning complete!')
        
        newEntityNo = len(list(self.entities.keys()))
        newRelationNo = len(self.relations)
        
        totalEntitiesRemoved = startingEntityNo - newEntityNo
        totalRelationsRemoved = startingRelationNo - newRelationNo
        relativeEntitiesRemoved = totalEntitiesRemoved / startingEntityNo
        relativeRelationsRemoved = totalRelationsRemoved / startingRelationNo
        
        if verbose: print(f'Removed {totalEntitiesRemoved} ({relativeEntitiesRemoved}%) entities.')
        if verbose: print(f'Removed {totalRelationsRemoved} ({relativeRelationsRemoved}%) relations.')


    def RemoveDuplicateRelations(self, relationList, verbose=False):
        listLength = len(relationList)
        totalRemoved = 0
        i = 0
        
        while i < listLength:
            toCompare = relationList[i][0]
        
            j = i+1
            while j < listLength:
                if relationList[j][0] == toCompare: 
                    relationList.pop(j)
                    listLength -= 1
                    totalRemoved += 1
                    j -= 1
                    
                j += 1
                
            i += 1
                    

        if verbose: print('Removed ' + str(totalRemoved) + ' duplicates.')
        return


    def RemoveEntity(self, entity, verbose=False):
        if verbose: print(f'Removing entity "{entity}"...')
        
        self.entities.pop(entity)
                
        i = 0
        while i < len(self.relations):
                    
            if self.relations[i][0]['head'] == entity or self.relations[i][0]['tail'] == entity:
                if verbose: print(f'\tRemoving containing relation "{self.relations[i]}"...')
                self.relations.pop(i)
                i -= 1
                        
            i += 1
                
        if verbose: print('\n')
        
        return
    

    def ResetAllLevels(self):
        print('WARNING: RESETTING ALL ENTITY LEVELS FROM THIS GRAPH!')
        for entity in self.entities.keys():
            self.entities[entity]['level'] = -1


    def ToColorCode(self, integer):
        if integer < 0:
            return '#aeaeae'
        elif integer <= 255: 
            red = 255
            green = integer
        else:
            green = 255
            red = 255 - (integer - 255)
            
        redHex = str(hex(red))[2:4].zfill(2)
        greenHex = str(hex(green))[2:4].zfill(2)
        colorCode = '#' + redHex + greenHex + '00'
            
        return colorCode


    def Visualize(self, filename='index.html', verbose=False):
        if(verbose): print('Generating .html graph file...')        

        net = Network(directed=True, width="1000px", height="1000px", bgcolor="#EEEEEE")
        net.toggle_physics(False)
        net.toggle_stabilization(False)
        net.set_edge_smooth('dynamic')
        net.show_buttons()

        for entity in self.entities:
            nodeSize = 10+1.1*self.entities[entity]['importance']
            entityLevel = self.entities[entity]['level']
            if nodeSize > 50: nodeSize = 50
            
            net.add_node(entity, shape='dot', color=self.ToColorCode(entityLevel), size=nodeSize)

        for relation in self.relations:
            net.add_edge(relation[0]['head'],relation[0]['tail'],title=relation[0]['type'],label=relation[0]['type'],value=5*relation[1])

        return net.generate_html(filename)
        

    def VisualizeEntityRelations(self, entity, filename='KnowledgeSubGraph.html', numberOfLevels=0, verbose=False):
        if verbose: print('Getting relations...')        

        relationEntityList = self.GetEntityRelations(entity, numberOfLevels=numberOfLevels)
        relationList = relationEntityList[0]
        entityDict = relationEntityList[1]
        
        if verbose: print('Cleaning relation list...')
        self.RemoveDuplicateRelations(relationList)
        
        if verbose: print('Generating html graph...')
        
        net = Network(directed=True, width="1000px", height="1000px", bgcolor="#EEEEEE")
        net.toggle_physics(False)
        net.toggle_stabilization(False)
        net.set_edge_smooth('dynamic')
        net.show_buttons()

        for entity in entityDict:
            nodeSize = 10+1.1*entityDict[entity]['importance']
            entityLevel = self.entities[entity]['level']
            if nodeSize > 50: nodeSize = 50
            
            net.add_node(entity, shape='dot', color=self.colorCodes[entityLevel], size=nodeSize)

        for relation in relationList:
            net.add_edge(relation[0]['head'],relation[0]['tail'],title=relation[0]['type'],label=relation[0]['type'],value=5*relation[1])

        net.save_graph(filename)