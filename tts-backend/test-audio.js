import { generateAudioForMessage } from './utils/audioGenerator.js';

const text = "My thoughts are under my control.";

const filePath = generateAudioForMessage(text);
console.log("Generated audio at:", filePath);
