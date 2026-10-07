export default [
    {
        name: 'users.setup',
        testMatch: /users\.setup\.ts/,
        dependencies: ['database.setup'],
    },
];
