import wikipedia

class Crawler:
    def __init__(self, startingPage, maxLevel=4):
        self.curLevel = 0
        self.maxLevel = maxLevel
        self.curPage = wikipedia.page(startingPage)
        self.nextPage = None
        self.savedLinks = [[startingPage]]
        self.isFinished = False
        self.alreadyRead = []

    def NextPage(self):
        if self.isFinished: 
            return

        if self.curLevel < 0:
            self.isFinished = True
            return
        
        if self.curLevel == self.maxLevel:
            if self.savedLinks[self.curLevel] == []:
                self.savedLinks.pop(self.curLevel)
                self.curLevel -= 1
                
            try:
                self.curPage = wikipedia.page(self.savedLinks[self.curLevel].pop(-1))
            except:
                pass

        else:
            if self.savedLinks[self.curLevel] == []:
                self.savedLinks.pop(self.curLevel)
                self.curLevel -= 1
            
            else:
                try:
                    self.savedLinks.append(self.curPage.links)
                    self.curPage = wikipedia.page(self.savedLinks[self.curLevel].pop(-1))
                    self.curLevel += 1
                except:
                    pass

        return

    def ExtractPageText(self):
        return self.curPage.content

    def ExtractPageTitle(self):
        return self.curPage.title

    def TreePrint(self):
        try:
            print('\t'*self.curLevel + self.curPage.title + f' -- {len(self.savedLinks[self.curLevel])} left!')
        except:
            pass
        return