// Allow importing CSS files from third-party packages
declare module '*.css' {
    const content: Record<string, string>;
    export default content;
}
