import torch
from parler_tts import ParlerTTSForConditionalGeneration
from transformers import AutoTokenizer
import soundfile as sf
import os

device = "cuda" if torch.cuda.is_available() else "cpu"

model = ParlerTTSForConditionalGeneration.from_pretrained("ai4bharat/indic-parler-tts").to(device)
text_tokenizer = AutoTokenizer.from_pretrained("ai4bharat/indic-parler-tts")
desc_tokenizer = AutoTokenizer.from_pretrained(model.config.text_encoder._name_or_path)

text = "Believe in yourself and all that you are. KNOW that there is something inside you, that is greater than any obstacle."

descriptions = {
    "Divya": "Divya's voice is calm and inspiring, with a gentle tone and clear delivery.",
    "Rohit": "Rohit speaks with confidence and energy, perfect for motivating others.",
    "Karan": "Karan has a deep and assertive voice, delivering words with strength and clarity.",
    "Leela": "Leela's voice is soft and soothing, ideal for conveying encouragement and warmth.",
    "Maya": "Maya speaks with enthusiasm and emotion, bringing energy to every sentence.",
    "Sita": "Sita has a clear and articulate voice, slightly fast-paced and expressive."
}

output_dir = "parler_speaker_named_tests"
os.makedirs(output_dir, exist_ok=True)

for name, desc in descriptions.items():
    desc_ids = desc_tokenizer(desc, return_tensors="pt", padding=True)
    text_ids = text_tokenizer(text, return_tensors="pt", padding=True)

    output = model.generate(
        input_ids=desc_ids.input_ids.to(device),
        attention_mask=desc_ids.attention_mask.to(device),
        prompt_input_ids=text_ids.input_ids.to(device),
        prompt_attention_mask=text_ids.attention_mask.to(device)
    )

    audio = output.cpu().numpy().squeeze()
    filepath = os.path.join(output_dir, f"{name.lower()}_motivational.wav")
    sf.write(filepath, audio, model.config.sampling_rate)
    print(f"✅ Saved: {filepath}")
