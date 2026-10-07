const fs = require('fs');
const path = require('path');

function renameFilesAndFolders(dir) {
    const files = fs.readdirSync(dir);

    files.forEach((file) => {
        const fullPath = path.join(dir, file);

        // Ignoriamo le cartelle di sistema per non fare danni
        if (
            ['node_modules', 'vendor', '.git'].some((ignore) =>
                fullPath.includes(ignore),
            )
        ) {
            return;
        }

        // Se è una cartella, andiamo prima in profondità
        if (fs.statSync(fullPath).isDirectory()) {
            renameFilesAndFolders(fullPath);
        }

        // Se il nome contiene saucebase (case-insensitive)
        if (file.toLowerCase().includes('saucebase')) {
            const newName = file.replace(/saucebase/gi, (match) => {
                return match === 'Saucebase' ? 'Miocoach' : 'miocoach';
            });

            const newPath = path.join(dir, newName);
            fs.renameSync(fullPath, newPath);
            console.log(`Rinominato: ${file} -> ${newName}`);
        }
    });
}

console.log('Inizio la rinominazione...');
renameFilesAndFolders(__dirname);
console.log('Finito!');
