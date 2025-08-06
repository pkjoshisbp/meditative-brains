from TTS.api import TTS

# Initialize the TTS model
tts = TTS(model_name="tts_models/multilingual/multi-dataset/xtts_v2").to("cpu")

# Path to your reference audio file
speaker_wav = "./myvoice.wav"

# Text you want to synthesize
text = "आप में असीम क्षमता है, इसकी कोई सीमा नहीं है। आप के दृढ़ संकल्प के आगे, कोई भी बाधा छोटी और नगण्य है। आप एक सकारात्मक विचारक हैं और अपने जीवन में केवल सकारात्मकता को ही आकर्षित करते हैं।"

# Language of the text
language = "hi"

# Generate and save the speech
tts.tts_to_file(text=text, speaker_wav=speaker_wav, language=language, file_path="hindi_test.wav")
