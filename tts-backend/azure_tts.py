import requests
import os
from pathlib import Path

# 🔁 Replace these with your Azure Speech resource values
AZURE_KEY = os.getenv("AZURE_SPEECH_KEY", "your-azure-speech-key-here")
AZURE_REGION = os.getenv("AZURE_SPEECH_REGION", "centralindia")  # e.g., "eastus"
VOICE_NAME = "en-US-AriaNeural"

# 💬 SSML Text
ssml = """
<speak version='1.0' xml:lang='en-US'>
  <voice name='en-US-AvaMultilingualNeural'>
    <prosody rate='slow'>You are calm and focused.</prosody>
    <break time='1500ms'/>
    You believe in yourself.
  </voice>
</speak>

"""

# 🌐 Azure TTS Endpoint
endpoint = f"https://{AZURE_REGION}.tts.speech.microsoft.com/cognitiveservices/v1"

# 📤 Make the request
headers = {
    "Ocp-Apim-Subscription-Key": AZURE_KEY,
    "Content-Type": "application/ssml+xml",
    "X-Microsoft-OutputFormat": "audio-16khz-128kbitrate-mono-mp3",
    "User-Agent": "MotivationAppClient"
}

response = requests.post(endpoint, headers=headers, data=ssml.encode("utf-8"))

# 💾 Save output
output_path = Path("azure_sample.mp3")
if response.status_code == 200:
    output_path.write_bytes(response.content)
    print(f"✅ Audio saved to {output_path.resolve()}")
else:
    print(f"❌ Error {response.status_code}: {response.text}")
