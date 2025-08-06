import fs from 'fs';
import axios from 'axios';

const AZURE_REGION = process.env.AZURE_SPEECH_REGION || 'centralindia';
const AZURE_KEY = process.env.AZURE_SPEECH_KEY || 'your-azure-speech-key-here';
const OUTPUT_FILE = './azure-voices.json';

async function fetchVoices() {
  try {
    const response = await axios.get(
      `https://${AZURE_REGION}.tts.speech.microsoft.com/cognitiveservices/voices/list`,
      {
        headers: {
          'Ocp-Apim-Subscription-Key': AZURE_KEY
        }
      }
    );
    fs.writeFileSync(OUTPUT_FILE, JSON.stringify(response.data, null, 2));
    console.log(`Voices list saved to ${OUTPUT_FILE}`);
  } catch (err) {
    console.error('Failed to fetch voices:', err.message);
  }
}

fetchVoices();
