const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const args = process.argv.slice(2);
const releaseType = args[0] || 'patch'; // major, minor, patch

// Files
const packageJsonPath = path.resolve(__dirname, '../package.json');
const pluginFilePath = path.resolve(__dirname, '../wp-force-repair.php');

// Helper: Get new version
function getNewVersion(current, type) {
    const parts = current.split('.').map(Number);
    if (type === 'major') {
        parts[0]++;
        parts[1] = 0;
        parts[2] = 0;
    } else if (type === 'minor') {
        parts[1]++;
        parts[2] = 0;
    } else {
        parts[2]++;
    }
    return parts.join('.');
}

// 1. Update package.json
const packageJson = require(packageJsonPath);
const oldVersion = packageJson.version;
const newVersion = getNewVersion(oldVersion, releaseType);

console.log(`🚀 Bumping version: ${oldVersion} -> ${newVersion}`);

packageJson.version = newVersion;
fs.writeFileSync(packageJsonPath, JSON.stringify(packageJson, null, 2) + '\n');

// 2. Update wp-force-repair.php
let pluginContent = fs.readFileSync(pluginFilePath, 'utf8');

// Update Header Version
pluginContent = pluginContent.replace(/Version:\s*(\d+\.\d+\.\d+)/, `Version: ${newVersion}`);
// Update Constant Version
pluginContent = pluginContent.replace(/define\s*\(\s*'WFR_VERSION',\s*'(\d+\.\d+\.\d+)'\s*\);/, `define( 'WFR_VERSION', '${newVersion}' );`);

fs.writeFileSync(pluginFilePath, pluginContent);

console.log('✅ Updated file versions.');

// 3. Git Commit & Tag
try {
    console.log('📦 Committing and Tagging...');
    execSync(`git add package.json wp-force-repair.php`);
    execSync(`git commit -m "chore: release v${newVersion}"`);
    execSync(`git tag v${newVersion}`);
    console.log(`🎉 Switched to v${newVersion}. Push with: git push && git push --tags`);
} catch (error) {
    console.error('❌ Git commands failed:', error.message);
}
