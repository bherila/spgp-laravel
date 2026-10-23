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
  preset: 'ts-jest',
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
    '^.+\\.(ts|tsx)$': ['ts-jest', { tsconfig: 'tsconfig.json' }],
    '^.+\\.m?js$': ['ts-jest', { tsconfig: { allowJs: true, module: 'commonjs', target: 'es2022' }, diagnostics: false }],
  },
};

module.exports = config;