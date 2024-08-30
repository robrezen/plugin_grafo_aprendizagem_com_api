import jsonpickle as jspickle

class FileHandler:
    def __init__(self, filePath):
        self.filePath = filePath
        
    def Write(self, content):
        file = open(self.filePath, "w")
        encodedContent = jspickle.dumps(content)
        file.write(encodedContent)
        file.close()
    
    def Load(self):
        file = open(self.filePath, "r")
        encodedContent = file.read()
        decodedContent = jspickle.loads(encodedContent)
        return decodedContent




