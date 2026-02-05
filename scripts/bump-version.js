const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const type = process.argv[2] || 'patch'; // 'patch', 'minor', 'major'
const validTypes = ['patch', 'minor', 'major'];

if (!validTypes.includes(type)) {
    console.error(`Invalid type: ${type}. Usage: npm run bump [patch|minor|major]`);
    process.exit(1);
}

// 1. Bump package.json (let npm handle the logic)
console.log(`Bumping ${type} version...`);
try {
    execSync(`npm version ${type} --no-git-tag-version`);
} catch (e) {
    console.error("Failed to bump package version.");
    process.exit(1);
}

const packageJson = require('../package.json');
const newVersion = packageJson.version;
console.log(`New Version: ${newVersion}`);

// 2. Update wp-force-repair.php
const phpPath = path.join(__dirname, '../wp-force-repair.php');
let phpContent = fs.readFileSync(phpPath, 'utf8');

// Regex to replace Version: X.X.X
phpContent = phpContent.replace(/Version: \d+\.\d+\.\d+/, `Version: ${newVersion}`);

// Regex to replace define( 'WFR_VERSION', ... )
phpContent = phpContent.replace(
    /define\( 'WFR_VERSION', '[^']+' \);/,
    `define( 'WFR_VERSION', '${newVersion}' );`
);

fs.writeFileSync(phpPath, phpContent);
console.log(`Updated wp-force-repair.php to ${newVersion}`);

// 3. Stage changes
try {
    execSync('git add package.json wp-force-repair.php');
    console.log('Staged package.json and wp-force-repair.php');
} catch (e) {
    console.error("Failed to stage files.");
}

console.log(`\nSUCCESS! Version bumped to ${newVersion}. Commit your changes to release.`);
