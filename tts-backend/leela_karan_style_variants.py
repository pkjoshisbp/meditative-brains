import torch
from parler_tts import ParlerTTSForConditionalGeneration
from transformers import AutoTokenizer
import soundfile as sf
import os

device = "cuda" if torch.cuda.is_available() else "cpu"

model = ParlerTTSForConditionalGeneration.from_pretrained("ai4bharat/indic-parler-tts").to(device)
text_tokenizer = AutoTokenizer.from_pretrained("ai4bharat/indic-parler-tts")
desc_tokenizer = AutoTokenizer.from_pretrained(model.config.text_encoder._name_or_path)

text = "Every step you take brings you closer to your goal. Keep going."

styles = {
    "Leela": [
        "Leela's voice is soft and soothing, ideal for conveying encouragement.",
        "Leela speaks with calmness and a slight emotional undertone.",
        "Leela's delivery is gentle but firm, suitable for reassurance.",
        "Leela has a nurturing tone, almost whisper-like.",
    ],
    "Karan": [
        "Karan's voice is deep and assertive, delivering words with confidence.",
        "Karan speaks with a motivational tone, moderately fast-paced.",
        "Karan's delivery is energetic and strong, perfect for inspiration.",
        "Karan has a commanding voice with a calm yet powerful presence.",
    ]
}

output_dir = "parler_style_variations"
os.makedirs(output_dir, exist_ok=True)

for speaker, descriptions in styles.items():
    for idx, desc in enumerate(descriptions, start=1):
        desc_enc = desc_tokenizer(desc, return_tensors="pt", padding=True)
        text_enc = text_tokenizer(text, return_tensors="pt", padding=True)

        output = model.generate(
            input_ids=desc_enc.input_ids.to(device),
            attention_mask=desc_enc.attention_mask.to(device),
            prompt_input_ids=text_enc.input_ids.to(device),
            prompt_attention_mask=text_enc.attention_mask.to(device)
        )

        audio = output.cpu().numpy().squeeze()
        filename = f"{speaker.lower()}_style_{idx}.wav"
        sf.write(os.path.join(output_dir, filename), audio, model.config.sampling_rate)
        print(f"✅ Saved: {filename}")
