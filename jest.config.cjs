/** @type {import('@jest/types').Config.InitialOptions} */
// React Markdown's ESM dependency family needs the same CommonJS transform as tests.
const markdownEsmPackages = [
  '@ungap/structured-clone', 'bail', 'ccount', 'character-[^/]+', 'comma-separated-tokens',
  'decode-named-character-reference', 'devlop', 'escape-string-regexp', 'estree-util-[^/]+',
  'hast-util-[^/]+', 'html-url-attributes', 'is-[^/]+', 'longest-streak', 'markdown-table',
  'mdast-util-[^/]+', 'micromark[^/]*', 'parse-entities', 'property-information',
  'react-markdown', 'remark-[^/]+', 'space-separated-tokens', 'stringify-entities',
  'trim-lines', 'trough', 'unified', 'unist-util-[^/]+', 'vfile[^/]*', 'zwitch',
].join('|');

const config = {
  testEnvironment: 'jsdom',
  testMatch: ['<rootDir>/resources/js/**/*.test.ts?(x)', '<rootDir>/tests-ts/**/*.test.ts?(x)'],
  setupFilesAfterEnv: ['<rootDir>/tests-ts/jest.setup.ts'],
  moduleNameMapper: {
    '^@/(.*)$': '<rootDir>/resources/js/$1',
  },
  transformIgnorePatterns: [
    `/node_modules/(?!(?:\\.pnpm|dayjs|${markdownEsmPackages})(?:/|$))`,
  ],
  moduleFileExtensions: ['ts', 'tsx', 'js', 'jsx', 'json', 'node'],
  transform: {
    '^.+\\.(ts|tsx)$': ['@swc/jest', {
      jsc: {
        parser: { syntax: 'typescript', tsx: true },
        transform: { react: { runtime: 'automatic' } },
        target: 'es2022',
      },
    }],
    '^.+\\.m?js$': ['@swc/jest', {
      jsc: {
        parser: { syntax: 'ecmascript' },
        target: 'es2022',
      },
    }],
  },
};

module.exports = config;