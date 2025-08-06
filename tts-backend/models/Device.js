// models/Device.js
import mongoose from 'mongoose';

const deviceSchema = new mongoose.Schema({
    userId: {
        type: mongoose.Schema.Types.ObjectId,
        ref: 'User',
        required: true
    },
    username: {
        type: String,
        required: true
    },
    deviceId: {
        type: String,
        required: true
    }
}, {
    timestamps: true
});

// Create a compound index to ensure one device per user, but allow same deviceId for different users
deviceSchema.index({ userId: 1, deviceId: 1 }, { unique: true });

const Device = mongoose.model('Device', deviceSchema);
export default Device;
