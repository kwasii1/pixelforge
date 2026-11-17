export type AppNotification = {
    id: string;
    generation_id: string;
    status: 'succeeded' | 'failed';
    title: string;
    prompt: string;
    detail: string;
    read: boolean;
    createdAt: string;
};

export type Notifications = {
    unreadCount: number;
    recent: AppNotification[];
};
