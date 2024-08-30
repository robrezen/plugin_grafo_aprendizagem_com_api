from typing import Any
from fastapi import FastAPI, Request, BackgroundTasks
from fastapi.responses import HTMLResponse, JSONResponse
from fastapi.middleware.cors import CORSMiddleware
from ApiRepository import ApiRepository

from Models import DetachGraphRequest, GraphPruneRequest, MergeGraphsRequest, NewGraphRequest, GraphHtmlRequest, UpdateGraphRequest, UpdateLevelRequest, UpdateLevelsRequest


app = FastAPI(
    title="Learning Graph API",
    description="Api to process data and call Moodle's webhook",
    version="0.0.1",
)
CORSMiddleware(app, allow_origins=["*"], allow_methods=["*"], allow_headers=["*"])

@app.exception_handler(Exception)
def on_callback(request: Request, exc: Exception):
    return JSONResponse(status_code=500, content={"message": "internal server error"})

###################################################################################################

@app.get("/")
def root():
    return {"message": "ok"}


@app.post("/NewGraph")
def GetNewGraph(newGraphRequest: NewGraphRequest, backgroundTasks: BackgroundTasks):
    backgroundTasks.add_task(ApiRepository.GetNewGraph, newGraphRequest)
    return {"message": "Task queued successfully."}


@app.post("/NewGraphExpanded")
def GetNewGraph(newGraphRequest: NewGraphRequest, backgroundTasks: BackgroundTasks):
    backgroundTasks.add_task(ApiRepository.GetNewGraphExpanded, newGraphRequest)
    return {"message": "Task queued successfully."}
    

@app.post("/GraphHtml")
def GetGraphHtml(graphHtmlRequest: GraphHtmlRequest, backgroundTasks: BackgroundTasks):
    backgroundTasks.add_task(ApiRepository.GetGraphHtml, graphHtmlRequest)
    return {"message": "Task queued successfully."}


@app.post("/PruneGraph")
def PruneGraph(graphPruneRequest: GraphPruneRequest, backgroundTasks: BackgroundTasks):
    backgroundTasks.add_task(ApiRepository.PruneGraph, graphPruneRequest)
    return {"message": "Task queued successfully."}


@app.put("/Graph")
def UpdateGraph(updateGraphRequest: UpdateGraphRequest, backgroundTasks: BackgroundTasks):
    backgroundTasks.add_task(ApiRepository.UpdateGraph, updateGraphRequest)
    return {"message": "Task queued successfully."}


@app.put("/GraphLevel")
def UpdateGraphLevel(updateLevelRequest: UpdateLevelRequest, backgroundTasks: BackgroundTasks):
    backgroundTasks.add_task(ApiRepository.UpdateGraphLevel, updateLevelRequest)
    return {"message": "Task queued successfully."}


@app.put("/MergeGraphs")
def MergeGraphs(mergeGraphsRequest: MergeGraphsRequest, backgroundTasks: BackgroundTasks):
    backgroundTasks.add_task(ApiRepository.MergeGraphs, mergeGraphsRequest)
    return {"message": "Task queued successfully."}


@app.put("/DetachGraph")
def DetachGraph(detachGraphRequest: DetachGraphRequest, backgroundTasks: BackgroundTasks):
    backgroundTasks.add_task(ApiRepository.DetachGraph, detachGraphRequest)
    return {"message": "Task queued successfully."}


@app.put("/UpdateLevels")
def UpdateLevels(updateLevelsRequest: UpdateLevelsRequest, backgroundTasks: BackgroundTasks):
    backgroundTasks.add_task(ApiRepository.UpdateLevels, updateLevelsRequest)
    return {"message": "Task queued successfully."}    