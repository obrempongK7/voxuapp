const fs = require('fs');
const jsCode = fs.readFileSync('./assets/js/voxu.js', 'utf8');

// A simple dom mock and other browser mocks
global.document = {
  querySelector: function(selector) {
    if (selector === 'meta[name="csrf-token"]') {
       if (this._csrfToken) {
         return {
           getAttribute: (name) => {
             if (name === 'content') return this._csrfToken;
             return null;
           }
         };
       }
       return null;
    }
    return null;
  },
  querySelectorAll: () => [],
  addEventListener: () => {},
  cookie: '',
  documentElement: { setAttribute: () => {}, removeAttribute: () => {}, style: {} },
  getElementById: () => null,
  createElement: () => ({ style: {}, classList: { add: () => {}, toggle: () => {}, remove: () => {} }, setAttribute: () => {}, appendChild: () => {}, removeChild: () => {} }),
  body: { appendChild: () => {}, removeChild: () => {} }
};

global.window = {
  location: { pathname: '/' },
  addEventListener: () => {},
  scrollY: 0,
  innerHeight: 800
};

global.navigator = {
  clipboard: { writeText: () => Promise.resolve() }
};

// Evaluate the entire file
// We need to evaluate it in the context of the current script
// or just wrap it and return the functions we want.
// Since it has a lot of globals, let's wrap it in a function that returns getCsrfToken.
const wrappedCode = `
  (function(document, window, navigator) {
    ${jsCode}
    return { getCsrfToken };
  })(global.document, global.window, global.navigator);
`;

let exportedFuncs;
try {
  exportedFuncs = eval(wrappedCode);
} catch (e) {
  console.error("Error evaluating voxu.js", e);
  process.exit(1);
}

const getCsrfToken = exportedFuncs.getCsrfToken;

function testGetCsrfToken() {
  console.log("Running testGetCsrfToken...");

  if (typeof getCsrfToken !== 'function') {
      console.error("❌ getCsrfToken function is not defined.");
      return false;
  }

  // Test empty DOM (no meta tag)
  global.document._csrfToken = null;
  const token1 = getCsrfToken();
  if (token1 !== '') {
    console.error("❌ Failed empty DOM test. Expected '', got " + token1);
    return false;
  }

  // Test with meta tag
  global.document._csrfToken = 'test-token-123';
  const token2 = getCsrfToken();
  if (token2 !== 'test-token-123') {
    console.error("❌ Failed token present test. Expected 'test-token-123', got " + token2);
    return false;
  }

  console.log("✅ testGetCsrfToken passed!");
  return true;
}

if (!testGetCsrfToken()) {
  process.exit(1);
}
