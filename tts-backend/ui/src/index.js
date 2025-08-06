// filepath: /var/www/clients/client1/web51/web/ui/src/index.js
import React from 'react';
import ReactDOM from 'react-dom/client'; // Use the new ReactDOM API for React 18+
import './index.css';
import App from './App';

// Create a root and render the App component
const root = ReactDOM.createRoot(document.getElementById('root'));
root.render(
  <React.StrictMode>
    <App />
  </React.StrictMode>
);
