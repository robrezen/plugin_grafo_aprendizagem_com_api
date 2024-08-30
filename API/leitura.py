import xml.etree.ElementTree as ET
from bs4 import BeautifulSoup
import re

def remove_html_tags(text):
    soup = BeautifulSoup(text, "html.parser")
    return soup.get_text()

def extract_answer(question_text):
    answers = []
    filter_answer = r'\{[^{}]*\}'
    filter_type_answer = r':(.*?):'
    text_answers = re.findall(filter_answer, question_text)
    for answer in text_answers:
        print(answer)
        type_answer = re.findall(filter_type_answer, answer)
        if( not type_answer):
            pattern = r'\{([^}]+)\}'
            answers +=re.findall( pattern , answer) 
        else: 
            if(type_answer[0] == 'NUMERICAL' or  type_answer[0] =='MCS' or type_answer[0] == 'SHORTANSWER_C'):
                pattern  = r'=(.*?)(?=\~|\})'
                answers += re.findall( pattern , answer)
            elif(type_answer[0] == 'MULTICHOICE_S'):
                pattern = r'%100%(.*?)(?:~|})'
                print(answer)
                answers += re.findall( pattern , answer)
                print(answers)
    return answers

def extract_questions(xml_file):
    tree = ET.parse(xml_file)
    root = tree.getroot()

    questions = []

    for question in root.findall(".//question"):
        question_text_element = question.find("questiontext/text")
        if question_text_element is not None:
            question_text = remove_html_tags(question_text_element.text.strip())
        else:
            continue

        answer_text = extract_answer(question_text)

        formatted_question = f"Questão: {question_text}\nRespostas:\n"
        for i, answer in enumerate(answer_text):
            formatted_question += f"{i + 1}. {answer}\n"

        questions.append(formatted_question)

    return questions

def save_as_text(questions, output_file):
    with open(output_file, "w", encoding="utf-8") as file:
        for question in questions:
            file.write(question + "\n\n")

if __name__ == "__main__":
    xml_file = "questoes.xml"  # Substitua pelo nome do seu arquivo XML do Moodle
    output_file = "questoes.txt"  # Nome do arquivo de texto de saída

    questions = extract_questions(xml_file)
    save_as_text(questions, output_file)

    print(f"As questões foram convertidas e salvas em '{output_file}'")