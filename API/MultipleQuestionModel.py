from transformers import AutoTokenizer, BertForMultipleChoice, Trainer, TrainingArguments
from datasets import load_dataset
from dataclasses import dataclass
from transformers.tokenization_utils_base import PreTrainedTokenizerBase, PaddingStrategy
from typing import Optional, Union

import torch
import evaluate
import numpy as np

@dataclass
class DataCollatorForMultipleChoice:
    """
    Data collator that will dynamically pad the inputs for multiple choice received.
    """

    tokenizer: PreTrainedTokenizerBase
    padding: Union[bool, str, PaddingStrategy] = True
    max_length: Optional[int] = None
    pad_to_multiple_of: Optional[int] = None

    def __call__(self, features):
        label_name = "label" if "label" in features[0].keys() else "labels"
        labels = [feature.pop(label_name) for feature in features]
        batch_size = len(features)
        num_choices = len(features[0]["input_ids"])
        flattened_features = [
            [{k: v[i] for k, v in feature.items()} for i in range(num_choices)] for feature in features
        ]
        flattened_features = sum(flattened_features, [])

        batch = self.tokenizer.pad(
            flattened_features,
            padding=self.padding,
            max_length=self.max_length,
            pad_to_multiple_of=self.pad_to_multiple_of,
            return_tensors="pt",
        )

        batch = {k: v.view(batch_size, num_choices, -1) for k, v in batch.items()}
        batch["labels"] = torch.tensor(labels, dtype=torch.int64)
        return batch
    


class MultipleQuestionModel(object):
    def __init__(self):
        self.tokenizer = AutoTokenizer.from_pretrained("bert-base-uncased")
        self.model = BertForMultipleChoice.from_pretrained("bert-base-uncased")
        self.accuracy = evaluate.load('accuracy')
        
        
    def test(self):
        prompt = "In Italy, pizza served in formal settings, such as at a restaurant, is presented unsliced."
        choice0 = "It is eaten with a fork and a knife."
        choice1 = "It is eaten while held in the hand."
        labels = torch.tensor(0).unsqueeze(0)

        encoding = self.tokenizer([prompt, prompt], [choice0, choice1], return_tensors="pt", padding=True)
        outputs = self.model(**{k: v.unsqueeze(0) for k, v in encoding.items()}, labels=labels)
        
        loss = outputs.loss
        logits = outputs.logits
        
        print(outputs)
        
        
    def train(self):
        swag = load_dataset('swag', 'regular')
        tokenizedSwag = swag.map(self.trainingPreprocessExample, batched=True)
        
        trainingArgs = TrainingArguments(
            output_dir="my_awesome_swag_model",
            evaluation_strategy="epoch",
            save_strategy="epoch",
            load_best_model_at_end=True,
            learning_rate=5e-5,
            per_device_train_batch_size=16,
            per_device_eval_batch_size=16,
            num_train_epochs=3,
            weight_decay=0.01,
            push_to_hub=False,
        )
        
        trainer = Trainer(
            model=self.model,
            args=trainingArgs,
            train_dataset=tokenizedSwag["train"],
            eval_dataset=tokenizedSwag["validation"],
            tokenizer=self.tokenizer,
            data_collator=DataCollatorForMultipleChoice(tokenizer=self.tokenizer),
            compute_metrics=self.trainingCalculateAccuracy,
        )
        
        trainer.train()
        
        
    def trainingPreprocessExample(self, examples):        
        ending_names = ["ending0", "ending1", "ending2", "ending3"]
        
        first_sentences = [[context] * 4 for context in examples["sent1"]]
        question_headers = examples["sent2"]
        second_sentences = [
            [f"{header} {examples[end][i]}" for end in ending_names] for i, header in enumerate(question_headers)
        ]

        first_sentences = sum(first_sentences, [])
        second_sentences = sum(second_sentences, [])

        tokenized_examples = self.tokenizer(first_sentences, second_sentences, truncation=True)
        return {k: [v[i : i + 4] for i in range(0, len(v), 4)] for k, v in tokenized_examples.items()}
    

    def trainingCalculateAccuracy(self, pred):
        predictions, labels = pred
        predictions = np.argmax(predictions, axis=1)
        return self.accuracy.compute(predictions=predictions, references=labels)


