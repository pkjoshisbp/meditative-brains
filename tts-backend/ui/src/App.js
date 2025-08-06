import React, { useState, useEffect } from 'react';

function App() {
    const [categories, setCategories] = useState([]);
    const [language, setLanguage] = useState('en_us');
    const [category, setCategory] = useState('');
    const [ssml, setSsml] = useState('');

    useEffect(() => {
        fetch('/api/categories')
            .then(res => res.json())
            .then(data => setCategories(data));
    }, []);

    const handleSave = () => {
        fetch('/api/save-ssml', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ssml, language, category }),
        }).then(res => res.json())
          .then(data => alert(data.success ? 'Saved!' : 'Failed!'));
    };

    return (
        <div>
            <h1>SSML Configuration</h1>
            <label>
                Language:
                <input value={language} onChange={e => setLanguage(e.target.value)} />
            </label>
            <label>
                Category:
                <select value={category} onChange={e => setCategory(e.target.value)}>
                    {categories.map(cat => <option key={cat} value={cat}>{cat}</option>)}
                </select>
            </label>
            <label>
                SSML:
                <textarea value={ssml} onChange={e => setSsml(e.target.value)} />
            </label>
            <button onClick={handleSave}>Save</button>
        </div>
    );
}

export default App;
