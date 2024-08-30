import requests
import time
import Enums
import json

from typing import Optional

class MoodleWebhookCaller:
    def __init__(self, url: str, wstoken: str, wsfunction: str, moodlewsrestformat: str):
        self.url = url
        self.wstoken = wstoken
        self.wsfunction = wsfunction
        self.moodlewsrestformat = moodlewsrestformat
     
        
    def _return_url(self) -> str:
            return f"{self.url}{self.wstoken}&wsfunction={self.wsfunction}&{self.moodlewsrestformat}"
    

    def _retry(self, response: requests.Response, attempts: int, id: int, destination: Enums.DestinationType) -> bool:
            print(f'moodle response: {response.text}, id: {id}, destination: {destination}')
            try:
                msg: dict = response.json()
                request_sucess = msg.get('success') and response.ok
                if not request_sucess and attempts < 3:
                    print('Error calling the webservice. Trying again in 5 seconds.')
                    time.sleep(5)
                    return True
                
                elif not request_sucess and attempts == 3:
                    print('Error calling the webservice. Maximum attempts reached.')
                    return False
                    ## to do  create a notify router to send a message to the user
                
                elif request_sucess:
                    print('Webhook called successfully.')
                    return False
                
                print('Error calling the webservice.')
                return False
            except Exception as e:
                print(f'Error to parser moodle response: {e}')
                return False
    

    def call_moodle_webhook(self, id, data: dict, destination: Enums.DestinationType, attempts=0, userid: Optional[int] = None, questionid: Optional[int]=None) -> None:
        response = requests.post(self._return_url(), data={'id': id, 'data': data.get('graph'), 'destination': destination.name, 'userid': userid, 'questionid': questionid})
        if self._retry(response, attempts, id, destination):
            self.call_moodle_webhook(id, data, destination, attempts+1, userid, questionid)