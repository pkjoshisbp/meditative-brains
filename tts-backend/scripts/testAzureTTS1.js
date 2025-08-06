import fs from 'fs';
import axios from 'axios';
import path from 'path';

// ==== CONFIGURATION ====
const AZURE_KEY = process.env.AZURE_SPEECH_KEY || 'your-azure-speech-key-here';
const AZURE_REGION = process.env.AZURE_SPEECH_REGION || 'centralindia';
const OUTPUT_FILE = './test-output.mp3';

// Change these to test different voices/languages/text
const language = 'en-IN';
const speaker = 'en-US-AvaMultilingualNeural';
const text = 'This is a test of Azure TTS with Indian English accent.';
const style = ''; // e.g. 'cheerful'
const personality = ''; // e.g. 'Pleasant'

// ==== BUILD SSML ====
let ssml = `<?xml version="1.0"?>
<speak version="1.0" xmlns="http://www.w3.org/2001/10/synthesis"
       xmlns:mstts="http://www.w3.org/2001/mstts"
       xmlns:emo="http://www.w3.org/2009/10/emotionml"
       xml:lang="en-US">
  <voice name="en-US-AvaMultilingualNeural">
    <prosody rate="medium" pitch="+5%">
      <mstts:express-as style="motivational" styledegree="2.0">
        <p>
          You are now awakening....
          <break time="100ms"/>
          to the profound truth — 
          <prosody pitch="-2%" rate="-15%">
             that confidence flows.
          </prosody>
        </p>
        <p>
          <prosody pitch="+8%" rate="medium">
            from within your very essence...
          </prosody> 
         
        </p>
      </mstts:express-as>
    </prosody>
  </voice>
</speak>`;

// ==== SEND REQUEST ====
async function main() {
  try {
    const response = await axios.post(
      'https://centralindia.tts.speech.microsoft.com/cognitiveservices/v1',
      ssml,
      {
        headers: {
          'Ocp-Apim-Subscription-Key': AZURE_KEY,
          'Content-Type': 'application/ssml+xml',
          'X-Microsoft-OutputFormat': 'audio-24khz-160kbitrate-mono-mp3',
          'User-Agent': 'AzureTTS-TestScript'
        },
        responseType: 'arraybuffer'
      }
    );
    fs.writeFileSync(OUTPUT_FILE, response.data);
    console.log(`✅ Audio saved to ${OUTPUT_FILE}`);
  } catch (err) {
    if (err.response && err.response.data) {
      console.error('Azure TTS error details:', err.response.data.toString());
    }
    console.error('Azure TTS error:', err.message);
  }
}

main();
