import mongoose from 'mongoose';

const languageSchema = new mongoose.Schema({
  code: {
    type: String,
    required: true,
    unique: true
  },
  name: {
    type: String,
    required: true
  },
  localName: {
    type: String,
    required: true
  },
  isActive: {
    type: Boolean,
    default: true
  }
});

export default mongoose.model('Language', languageSchema);
