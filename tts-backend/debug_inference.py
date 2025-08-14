import os
import torch
from TTS.tts.configs.vits_config import VitsConfig
from TTS.tts.models.vits import Vits
from TTS.tts.utils.text.tokenizer import TTSTokenizer
from TTS.utils.audio import AudioProcessor

# Define paths
output_path = "/var/www/clients/client1/web63/web/tts-backend"
model_path = "/var/www/clients/client1/web63/web/tts-backend/vits_ljspeech-April-15-2025_05+11PM-0000000/best_model_60.pth"
config_path = "/var/www/clients/client1/web63/web/tts-backend/vits_ljspeech-April-15-2025_05+11PM-0000000/config.json"

# Load config
config = VitsConfig()
config.load_json(config_path)
config.audio.sample_rate = 22050  # Ensure correct sample rate

# Initialize AudioProcessor and Tokenizer
ap = AudioProcessor.init_from_config(config)
tokenizer, config = TTSTokenizer.init_from_config(config)

# Load model
model = Vits(config)
checkpoint = torch.load(model_path, map_location=torch.device("cpu"))
model.load_state_dict(checkpoint["model"], strict=False)  # Set strict to False
model.eval()

# Inference
text = "Hello Welcome to the World of AI Assistant"
sequence = torch.LongTensor(tokenizer.text_to_ids(text)).unsqueeze(0)

# Set inference parameters
length_scale = 1.0
noise_scale = 0.667
noise_scale_w = 0.8

# Set model attributes for inference
model.length_scale = length_scale
model.noise_scale = noise_scale
model.noise_scale_w = noise_scale_w

# Run the model
with torch.no_grad():
    output = model.inference(sequence)
    
# Post-processing
waveform = ap.inv_melspectrogram(output[0].cpu().squeeze().numpy(), sample_rate=config.audio.sample_rate)

# Save the output
output_file = os.path.join(output_path, "generated_audio.wav")
ap.save_wav(waveform, output_file)

print(f"Generated audio saved to: {output_file}")
