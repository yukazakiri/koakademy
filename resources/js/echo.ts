import Echo from "laravel-echo";
import Pusher from "pusher-js";

declare global {
    interface Window {
        Pusher?: typeof Pusher;
        Echo?: Echo<"pusher">;
    }
}

const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY;

if (typeof pusherKey === "string" && pusherKey.length > 0) {
    window.Pusher = Pusher;

    window.Echo = new Echo({
        broadcaster: "pusher",
        key: pusherKey,
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
        forceTLS: true,
        enabledTransports: ["ws", "wss"],
    });
}

export default window.Echo;
