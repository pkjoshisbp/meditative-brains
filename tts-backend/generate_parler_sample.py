import torch
from parler_tts import ParlerTTSForConditionalGeneration
from transformers import AutoTokenizer
import soundfile as sf

device = "cuda" if torch.cuda.is_available() else "cpu"

# Load model and tokenizers
model = ParlerTTSForConditionalGeneration.from_pretrained("ai4bharat/indic-parler-tts").to(device)
text_tokenizer = AutoTokenizer.from_pretrained("ai4bharat/indic-parler-tts")
desc_tokenizer = AutoTokenizer.from_pretrained(model.config.text_encoder._name_or_path)

# Inputs
description = "a calm Indian female voice with moderate speed"
text = "You are stronger than you think. Keep going."

# Tokenize
desc_encoded = desc_tokenizer(description, return_tensors="pt", padding=True)
desc_ids = desc_tokenizer(description, return_tensors="pt").input_ids.to(device)
desc_mask = desc_encoded.attention_mask.to(device)

text_encoded = text_tokenizer(text, return_tensors="pt", padding=True)
text_ids = text_tokenizer(text, return_tensors="pt").input_ids.to(device)
text_mask = text_encoded.attention_mask.to(device)

# Generate
output = model.generate(
    input_ids=desc_ids,
    attention_mask=desc_mask,
    prompt_input_ids=text_ids,
    prompt_attention_mask=text_mask
)
audio = output.cpu().numpy().squeeze()


# Save
sf.write("motivational_sample.wav", audio, model.config.sampling_rate)
print("✅ Audio saved to motivational_sample.wav")
