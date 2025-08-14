from TTS.api import TTS

# Load your trained model
tts = TTS(
    model_path="/var/www/clients/client1/web63/web/tts-backend/vits_ljspeech-April-15-2025_05+11PM-0000000/best_model_60.pth",
    config_path="/var/www/clients/client1/web63/web/tts-backend/vits_ljspeech-April-15-2025_05+11PM-0000000/config.json",
    progress_bar=True,
    gpu=False
)

# Run synthesis
tts.tts_to_file(
    text="Hello, welcome to the world of AI assistant!",
    file_path="output_custom.wav"
)
