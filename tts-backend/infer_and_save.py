import os
import torch
import torchaudio
from TTS.utils.manage import ModelManager
from TTS.utils.synthesizer import Synthesizer
from TTS.config import load_config

# Paths
config_path = "./workspace/my-vits-checkpoints/hindi_vits_run-April-14-2025_10+02PM-0000000/config.json"
model_path = "./workspace/my-vits-checkpoints/hindi_vits_run-April-14-2025_10+02PM-0000000/best_model.pth"
output_wav_path = "./test_outputs/hindi_infer_output.wav"
output_dir = os.path.dirname(output_wav_path)
os.makedirs(output_dir, exist_ok=True)

# Load config and model
config = load_config(config_path)
synthesizer = Synthesizer(
    tts_checkpoint=model_path,
    tts_config_path=config_path,
    use_cuda=False,
)

# Text to synthesize
text = "आपका स्वागत है।"
wav = synthesizer.tts(text)

# Save to .wav
torchaudio.save(output_wav_path, torch.tensor([wav]), config.audio["sample_rate"])
print(f"✅ Saved output to {output_wav_path}")
