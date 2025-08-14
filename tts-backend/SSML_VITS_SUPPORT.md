# 🎵 SSML Support for VITS - Implementation Guide

## 🎯 **Overview**
While VITS doesn't natively support SSML like Azure TTS, we've implemented **SSML preprocessing** that extracts useful information from SSML tags and applies them to VITS parameters.

---

## ✅ **Supported SSML Tags**

### 1. **`<break>` - Pause Control**
```xml
<break time="1s"/>          <!-- 1 second pause -->
<break time="500ms"/>       <!-- 500ms pause -->
<break/>                    <!-- Default pause -->
```
**VITS Implementation:** Converts to text ellipsis (`, ..., ......`)

### 2. **`<prosody rate="">` - Speech Speed**
```xml
<prosody rate="slow">Slow speech</prosody>
<prosody rate="fast">Fast speech</prosody>
<prosody rate="120%">20% faster</prosody>
<prosody rate="0.8">80% speed</prosody>
```
**VITS Implementation:** Adjusts `length_scale` parameter
- `slow` → 0.75x speed (length_scale = 1.33)
- `fast` → 1.25x speed (length_scale = 0.8)
- `120%` → 1.2x speed (length_scale = 0.83)

### 3. **`<emphasis>` - Emphasis Control**
```xml
<emphasis level="strong">Strong emphasis</emphasis>
<emphasis level="moderate">Moderate emphasis</emphasis>
<emphasis level="reduced">Reduced emphasis</emphasis>
<emphasis>Default emphasis</emphasis>
```
**VITS Implementation:** Adjusts `noise_scale_w` (intonation variation)
- `strong` → +0.2 intonation, adds `**text**` markers
- `moderate` → +0.1 intonation, adds `*text*` markers
- `reduced` → -0.1 intonation

### 4. **`<say-as>` - Text Interpretation**
```xml
<say-as interpret-as="spell-out">NASA</say-as>
<say-as interpret-as="number">123</say-as>
<say-as interpret-as="date">2025-08-11</say-as>
```
**VITS Implementation:** 
- `spell-out` → Adds spaces: "N A S A"
- Numbers/dates → Passes through (VITS handles reasonably)

### 5. **`<sub>` - Substitution**
```xml
<sub alias="World Wide Web">WWW</sub>
```
**VITS Implementation:** Uses alias text for pronunciation

---

## 🔧 **Parameter Mapping**

| **SSML Feature** | **VITS Parameter** | **Effect** |
|------------------|-------------------|------------|
| `<prosody rate="slow">` | `length_scale: 1.33` | Slower speech |
| `<prosody rate="fast">` | `length_scale: 0.8` | Faster speech |
| `<emphasis level="strong">` | `noise_scale_w: +0.2` | More expressive |
| `<break time="1s">` | Text: `"... ... ..."` | Pause effect |

---

## 💡 **Usage Examples**

### **Example 1: Motivational Speech with Pauses**
```javascript
const ssml = `
<speak>
  Welcome to your <emphasis level="strong">daily motivation</emphasis>.
  <break time="1s"/>
  Remember, <prosody rate="slow">success comes to those who persevere</prosody>.
  <break time="500ms"/>
  You can <emphasis level="moderate">achieve anything</emphasis> you set your mind to!
</speak>`;

const options = {
  engine: 'vits',
  language: 'en-US',
  speaker: 'p225',
  ssml: ssml
};
```

**Result:**
- Text: `"Welcome to your **daily motivation**... ... ... Remember, success comes to those who persevere... You can *achieve anything* you set your mind to!"`
- Parameters: `length_scale: 1.33, noise_scale_w: 0.9`

### **Example 2: Speed Variations**
```javascript
const ssml = `
<speak>
  <prosody rate="slow">Think carefully</prosody> about your goals.
  <break time="800ms"/>
  Then <prosody rate="fast">take action immediately</prosody>!
</speak>`;
```

**Result:**
- Speed adjustments applied automatically
- Natural pauses inserted

---

## 🧪 **Testing SSML with VITS**

```javascript
// Test the SSML processing
import { parseSSMLForVITS } from './utils/ssmlParser.js';

const testSSML = `
<speak>
  <prosody rate="slow">Hello world</prosody>
  <break time="1s"/>
  <emphasis level="strong">This is important!</emphasis>
</speak>`;

const result = parseSSMLForVITS(testSSML);
console.log('Processed text:', result.text);
console.log('VITS parameters:', result.vitsParams);
```

---

## 📊 **Limitations & Workarounds**

### **❌ Not Supported:**
- `<voice>` changes (VITS uses single voice per generation)
- `<phoneme>` (VITS uses its own phoneme processing)
- `<audio>` inserts
- Complex prosody (pitch, volume)

### **✅ Workarounds:**
- **Multiple voices:** Generate separate audio files and concatenate
- **Phonemes:** Let VITS handle pronunciation naturally
- **Audio inserts:** Post-process audio files

---

## 🚀 **Production Benefits**

1. **🔄 Unified API:** Same SSML works for both Azure and VITS
2. **📈 Enhanced Expression:** VITS gets prosody hints from SSML
3. **⚡ Automatic Processing:** No code changes needed in frontend
4. **🎵 Better Audio:** More natural-sounding VITS output
5. **🛠️ Graceful Fallback:** Strips unsupported tags automatically

---

## 🎯 **Best Practices**

### **Do:**
- Use `<break>` for dramatic pauses
- Apply `<emphasis>` for key words
- Use `<prosody rate="">` for pace changes
- Keep SSML simple for VITS

### **Don't:**
- Nest complex tags deeply
- Expect exact Azure TTS behavior
- Use unsupported voice changes
- Over-complicate the markup

**Result: VITS now understands and applies SSML hints for more expressive speech! 🎉**
