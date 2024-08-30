#this is only a test file, it will be removed in the future
from fastapi import FastAPI, Request, Response, status, BackgroundTasks
from fastapi.responses import JSONResponse, RedirectResponse
from fastapi.encoders import jsonable_encoder
from fastapi.exceptions import RequestValidationError
from pydantic import BaseModel
from typing import Dict, Optional, Union
from datetime import datetime
from fastapi.middleware.cors import CORSMiddleware
from apscheduler.schedulers.asyncio import AsyncIOScheduler
from enum import Enum

import uvicorn
import requests
import argparse
import os
import sys
import time

sys.path.append(os.path.dirname(os.path.dirname(os.path.join(os.path.abspath(__file__), '..','api'))))

sched = AsyncIOScheduler()
sched.start()
WEBSERVICE_URL = 'http://localhost/webservice/rest/server.php?'
WSTOKEN = 'wstoken=8f2d09b4ebaae96a81fb618cf76bef72' #this token needs to be geenrated in moodle, this given permission to the api to call the webservice
WSFUNCTION = 'mod_learninggraph_ws_data_received'
MOODLEWSRESTFORMAT = 'moodlewsrestformat=json'

from graph_exemple import graph_data

app = FastAPI(
    title="Learning Graph API",
    description="Api to process data and call Moodle's webhook",
    version="0.0.1",
)
CORSMiddleware(app, allow_origins=["*"], allow_methods=["*"], allow_headers=["*"])

class MoodleData(BaseModel):
    data: Union[dict, str, list]
    cm_id: int

class DestinationType(Enum):
    basegraph = 'basegraph'
    aggregetegraph = 'aggregategraph'
    studentgraph = 'studentgraph'

class LearninggraphManager():
    def __init__(self, url: str, wstoken: str, wsfunction: str, moodlewsrestformat: str):
        self.url = url
        self.wstoken = wstoken
        self.wsfunction = wsfunction
        self.moodlewsrestformat = moodlewsrestformat
    
    def _return_url(self) -> str:
        return f"{self.url}{self.wstoken}&wsfunction={self.wsfunction}&{self.moodlewsrestformat}"
    
    def _retry(self, response: requests.Response, attempts: int) -> bool:
        if response.status_code != 200 and attempts < 3:
            print('Error calling the webservice. Trying again in 5 seconds.')
            time.sleep(5)
            return True
        elif response.status_code != 200 and attempts == 3:
            print('Error calling the webservice. Maximum attempts reached.')
            ## to do  create a notify router to send a message to the user
        elif response.status_code == 200:
            print('Webhook called successfully.')
            return False
        print('Error calling the webservice.')
        return False
    
    def _moodle_webhook(self, cm_id, data: dict, destination: DestinationType, attempts=0, userid: Optional[int] = None) -> None:
        response = requests.post(self._return_url(), data={'id': cm_id, 'data': data, 'destination': destination, 'userid': userid})
        if self._retry(response, attempts):
            self._moodle_webhook(cm_id, data, destination, attempts+1, userid)
        
    def generate_graph(self, cm_id: int, data: dict, destination: DestinationType) -> None:
        # data needs to transform in a graph
        self._moodle_webhook(cm_id, graph_data, destination)

    def process_questions(self, cm_id: int, questions: list) -> None:
        '''
            if time is to long to process, we can use the scheduler to process the questions? but several bacckground tasks is a good idea?
        '''
        for question in questions:
            self.generate_graph(question['id'], question['data'])
        #after finish the process, we need to call the webservice to notify the end of the process to improve the couse graph

    def merge_graphs(self, cm_id: int, base_graph: dict, new_graphs: Union[dict, list], destination: DestinationType, 
                                userscore: Optional[dict], userid: Optional[int] = None) -> None:
        '''
            We need to aggregate the base graph with the new graphs
        '''
        if destination == DestinationType.studentgraph:
            #function to change nodes color
            print('User score:', userscore)
        else:
            #function to merge the graphs
            print('Merging graphs')
        
        self._moodle_webhook(cm_id, graph_data, destination, userid=userid)

    def detach_graph(self, cm_id: int, base_graph: dict, to_remove: Union[dict, list], destination: DestinationType, userid: Optional[int] = None):
        '''
            We need to remove the nodes and edges from the graph
        '''
        self._moodle_webhook(cm_id, graph_data, DestinationType.studentgraph)

manager = LearninggraphManager(WEBSERVICE_URL, WSTOKEN, WSFUNCTION, MOODLEWSRESTFORMAT)

@app.get('/')
def root():
    return RedirectResponse(url='/docs') 


## process questions and lesson plans could be a single endpoint, but I think it is better to separate them
@app.post('/process_questions')
async def process_questions(moodleData: MoodleData, background_tasks: BackgroundTasks, userid: Optional[int] = None):
    print(moodleData)
    background_tasks.add_task(manager.process_questions, moodleData.cm_id, moodleData.data)
    return {"message": "Received Lesson. Processing in background."}


@app.post('/process_lessonplan')
async def process_lessonplan(moodleData: MoodleData, background_tasks: BackgroundTasks, userid: Optional[int] = None):
    print(moodleData)
    background_tasks.add_task(manager.generate_graph, moodleData.cm_id, moodleData.data, DestinationType.basegraph)
    return {"message": "Received data. Processing in background."}


@app.get('/merge_graphs')
async def merge_graphs(cm_id: int, base_graph: dict, new_graphs: Union[dict, list], destination: DestinationType, 
                            background_tasks: BackgroundTasks, userscore: Optional[dict], userid: Optional[int] = None):
    '''
        We need to aggregate the base graph with the new graphs.
            teacher add some questions
            user answer some questions (only changes color of the nodes and edges)
                - I need to think how get the user quiz score and how store this(probably a dict with question id and score)
    '''
    print(cm_id, base_graph, new_graphs)
    background_tasks.add_task(manager.merge_graphs(cm_id, base_graph, new_graphs, userscore, destination, userid))
    return {"message": "Graphs aggregated."}


@app.delete('/detach_graph')
async def detach_graph(cm_id: int, base_graph: dict, to_remove: Union[dict, list],
                       destination: DestinationType, background_tasks: BackgroundTasks, userid: Optional[int] = None):
    '''
       If theacher delete some questions, we need to remove the nodes and edges from the graph or
       change lesson plan (first we need to remove the graph and then create a new one with the new data)
    '''
    print(cm_id, base_graph, to_remove)
    background_tasks.add_task(manager.detach_graph, cm_id, base_graph, to_remove, destination, userid)
    return {"message": "Graph removed."}


@app.delete('cancel_process')
def cancel_process(cm_id: str, destination: DestinationType):
    '''
    We need to decide how to cancel the process. How manage the cancelation of the process? A queeue of process?
    '''
    if type == DestinationType.question:
        return {"message": "Process cancelled."}
    elif type == DestinationType.lessonplan:
        return {"message": "Process cancelled."}


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description='Run the Learning Graph API')
    parser.add_argument('--host', type=str, default='localhost', help='Host to run the API')
    parser.add_argument('--port', type=int, default=8000, help='Port to run the API')
    parser.add_argument('--workers', type=int, default=8, help='Number of workers to run the API')
    parser.add_argument('--log-level', type=str, default='info', help='Log level')
    parser.add_argument('--env', type=str, default='dev', help='Environment')
    return parser

if __name__ == "__main__":
    parser = build_parser()
    args = parser.parse_args()

    kwargs = {'reload': True} if args.env == 'dev' else {'workers': args.workes, 'reload': False, 'log_level': 'info', 'debug': False}
    uvicorn.run('api:app', host=args.host, port=args.port, **kwargs)
