import express from 'express';
import fs from 'fs';
import path from 'path';
import nodemailer from 'nodemailer';
import logger from '../utils/logger.js';

const router = express.Router();

// Create logs directory if it doesn't exist
const logsDir = path.join(process.cwd(), 'flutter_logs');
if (!fs.existsSync(logsDir)) {
  fs.mkdirSync(logsDir, { recursive: true });
  logger.info(`Created logs directory at: ${logsDir}`);
}

// Configure nodemailer transporter
const transporter = nodemailer.createTransport({
  host: 'server1.mywebsolutions.co.in',
  port: 587,
  secure: false, // true for 465, false for other ports
  auth: {
    user: 'pkjoshi@mywebsolutions.co.in',
    pass: 'pragati123..'
  },
  tls: {
    rejectUnauthorized: false
  },
  debug: true, // Enable debug output
});

// Verify SMTP connection
transporter.verify(function(error, success) {
  if (error) {
    logger.error('SMTP connection issue:', error);
  } else {
    logger.info('SMTP server is ready to take our messages');
  }
});

// Function to send email with logs
const sendEmail = async ({ to, subject, text, attachments }) => {
  try {
    logger.info(`Attempting to send email to: ${to}`);
    
    // First try to create a file-based attachment
    const fileAttachment = attachments[0];
    const attachmentPath = path.join(logsDir, fileAttachment.filename);
    
    const mailOptions = {
      from: '"Flutter App Logs" <pkjoshi@mywebsolutions.co.in>',
      to,
      subject,
      text,
      attachments: [{
        filename: fileAttachment.filename,
        path: attachmentPath
      }]
    };
    
    const info = await transporter.sendMail(mailOptions);
    logger.info(`Email sent: ${info.messageId}`);
    return true;
  } catch (error) {
    logger.error('Error sending email:', error);
    return false;
  }
};

// Route to handle logs
router.post('/', async (req, res) => {
  try {
    logger.info(`Received log request from ${req.ip}`);
    
    // Check if body is empty or not properly parsed
    if (!req.body || Object.keys(req.body).length === 0) {
      logger.error('Empty or invalid request body');
      return res.status(400).json({ success: false, error: 'Invalid request body' });
    }
    
    const { logs, timestamp, deviceInfo, source } = req.body;
    
    if (!logs) {
      logger.error('No logs provided in request');
      return res.status(400).json({ success: false, error: 'No logs provided' });
    }

    // Format timestamp for filename
    const dateTime = new Date().toISOString().replace(/[:.]/g, '-');
    const filename = `app_logs_${dateTime}.txt`;
    const filePath = path.join(logsDir, filename);

    logger.info(`Saving logs to: ${filePath}`);

    // Save logs to file synchronously to ensure it exists when we send the email
    try {
      fs.writeFileSync(filePath, logs);
      logger.info('Log file saved successfully');
      
      // Send via email
      try {
        const emailResult = await sendEmail({
          to: 'pkjoshi.sbp@gmail.com',
          subject: `App Logs: ${timestamp || dateTime}`,
          text: `Logs from ${source || 'Flutter App'} ${deviceInfo ? `(${deviceInfo})` : ''}`,
          attachments: [{ 
            filename, 
            content: logs 
          }]
        });
        
        logger.info(`Email sending result: ${emailResult}`);
        
        return res.status(200).json({ 
          success: true, 
          fileSaved: true,
          emailSent: emailResult
        });
      } catch (emailErr) {
        logger.error('Error in email sending:', emailErr);
        // Still return partial success if file was saved
        return res.status(207).json({
          success: true,
          fileSaved: true,
          emailSent: false,
          emailError: emailErr.message
        });
      }
    } catch (fileErr) {
      logger.error('Error saving log file:', fileErr);
      return res.status(500).json({ success: false, error: fileErr.message });
    }
    
  } catch (error) {
    logger.error('Error processing logs:', error);
    return res.status(500).json({ success: false, error: error.message });
  }
});

// Add a test route to verify the endpoint is working
router.get('/test', (req, res) => {
  logger.info('Test endpoint was called');
  res.status(200).json({ message: 'Logs API is working!' });
});

export default router;
