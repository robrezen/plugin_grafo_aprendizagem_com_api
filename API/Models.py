from typing import Any, Union, List
from pydantic import BaseModel
from Enums import DestinationType

class NewGraphRequest(BaseModel):
    text: str
    cm_id: int
    destination_type: DestinationType
    id: Union[int, None]

class GraphHtmlRequest(BaseModel):
    graph: str
    cm_id: int
    destination_type: DestinationType

class UpdateGraphRequest(BaseModel):
    text: str
    graph: str
    cm_id: int
    destination_type: DestinationType
    
class UpdateLevelRequest(BaseModel):
    text: str
    graph: str
    amount: int
    cm_id: int
    destination_type: DestinationType

class GraphPruneRequest(BaseModel):
    graph: str
    minLevel: int
    cm_id: int
    destination_type: DestinationType
    
class MergeGraphsRequest(BaseModel):
    graphBase: Any
    graphsToMerge: Any
    priorityToNegativeLevel: bool
    cm_id: int
    destination_type: DestinationType
    
class DetachGraphRequest(BaseModel):
    graphBase: str
    graphToDetach: str
    cm_id: int
    destination_type: DestinationType

class UpdateLevelsRequest(BaseModel):
    graphBase: str
    question_details: Any
    cm_id: int
    user_id: int
    destination_type: DestinationType