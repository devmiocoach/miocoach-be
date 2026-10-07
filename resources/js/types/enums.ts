export const Role = {
    ADMIN: 'admin',
    USER: 'user',
    SUBSCRIBER: 'subscriber',
    COACH: 'coach',
    CLIENT: 'client',
} as const;

export type RoleValue = (typeof Role)[keyof typeof Role];
