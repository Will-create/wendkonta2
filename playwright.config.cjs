const {defineConfig, devices} = require('@playwright/test');
module.exports = defineConfig({
    testDir: './tests/e2e', testMatch: '*.spec.cjs', fullyParallel: false, workers: 1,
    timeout: 90000, expect: {timeout: 15000}, retries: 0,
    outputDir: '/results/artifacts',
    reporter: [['list'], ['html', {outputFolder:'/results/html',open:'never'}], ['json',{outputFile:'/results/browser.json'}]],
    use: {baseURL:process.env.BASE_URL, trace:'retain-on-failure', screenshot:'only-on-failure', video:'retain-on-failure'},
    projects: [
        {name:'android-chromium',use:{...devices['Pixel 7'],browserName:'chromium'}},
        {name:'ios-webkit',use:{...devices['iPhone 13'],browserName:'webkit'}},
        {name:'desktop-chromium',use:{...devices['Desktop Chrome'],viewport:{width:1366,height:900},browserName:'chromium'}}
    ]
});
