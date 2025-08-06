import mongoose from 'mongoose';

const userSchema = new mongoose.Schema({
    username: { type: String, required: true, unique: true },
    password: { type: String, required: true },
    firstName: String,
    lastName: String,
    email: { type: String, required: true, unique: true },
    phone: String,
    age: Number,
    country: String,
    deviceId: String,
    categoryLimit: { type: Number, default: 10 } // Limit number of custom categories per user
});

const User = mongoose.model('User', userSchema);
export default User;
