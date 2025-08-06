import torch
from parler_tts import ParlerTTSForConditionalGeneration
from transformers import AutoTokenizer
import soundfile as sf
import os

device = "cuda" if torch.cuda.is_available() else "cpu"

model = ParlerTTSForConditionalGeneration.from_pretrained("ai4bharat/indic-parler-tts").to(device)
text_tokenizer = AutoTokenizer.from_pretrained("ai4bharat/indic-parler-tts")
desc_tokenizer = AutoTokenizer.from_pretrained(model.config.text_encoder._name_or_path)

text = "You are capable of achieving greatness. Keep moving forward."

descriptions = [
    "a calm Indian female voice",
    "a cheerful Indian male speaker",
    "a deep voice male speaker in Hindi",
    "a confident and fast-paced female voice",
    "a soft-spoken male voice",
    "a warm emotional female speaker in Hindi",
    "a motivational male voice with deep tone"
]

output_dir = "parler_voice_tests"
os.makedirs(output_dir, exist_ok=True)

for idx, desc in enumerate(descriptions, start=1):
    desc_enc = desc_tokenizer(desc, return_tensors="pt", padding=True)
    text_enc = text_tokenizer(text, return_tensors="pt", padding=True)

    input_ids = desc_enc["input_ids"].to(device)
    attention_mask = desc_enc["attention_mask"].to(device)
    prompt_ids = text_enc["input_ids"].to(device)
    prompt_mask = text_enc["attention_mask"].to(device)

    output = model.generate(
        input_ids=input_ids,
        attention_mask=attention_mask,
        prompt_input_ids=prompt_ids,
        prompt_attention_mask=prompt_mask
    )

    audio = output.cpu().numpy().squeeze()
    filename = f"sample_{idx}.wav"
    filepath = os.path.join(output_dir, filename)
    sf.write(filepath, audio, model.config.sampling_rate)

    print(f"✅ Saved: {filepath} | Description: {desc}")
