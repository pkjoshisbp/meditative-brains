from TTS.api import TTS

# Initialize TTS with specific XTTS model name
tts = TTS("tts_models/multilingual/multi-dataset/xtts_v2").to("cpu")

# For XTTS, we need both language and speaker_wav
tts.tts_to_file(
    text="यह मेरा खुद का प्रशिक्षित मॉडल है।",
    file_path="output.wav",
    language="hi",
    speaker_wav="./workspace/tts-dataset/wavs/0001.wav"  # Replace with path to any reference audio file
)
