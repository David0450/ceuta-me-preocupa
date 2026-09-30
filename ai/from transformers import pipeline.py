from transformers import pipeline  

# Load a summarization model
summarizer = pipeline("sentiment-analysis", model="facebook/bart-large-cnn")  

# Text to summarize
text = "Artificial intelligence is revolutionizing multiple industries..."  

# Generate a summary
summary = summarizer(text, max_length=50, min_length=10, do_sample=False)  
print(summary[0]['summary_text'])