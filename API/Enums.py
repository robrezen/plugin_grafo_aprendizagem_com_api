from enum import Enum

class DestinationType(Enum):
    basegraph = 'basegraph'
    questiongraph = 'questiongraph'
    aggregategraph = 'aggregategraph'
    studentgraph = 'studentgraph'
    lessonplangraph = 'lessonplangraph'