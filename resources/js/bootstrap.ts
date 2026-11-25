import { dispatchAppUpdateAvailable } from "@/lib/app-update";
import axios from "axios";

declare global {
    interface Window {
        axios: typeof axios;
    }
}

window.axios = axios;

window.axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";
window.axios.defaults.withCredentials = true;

window.axios.interceptors.response.use((response) => {
    const buildId = response.headers?.["x-app-build-id"];

    if (typeof buildId === "string" && buildId.length > 0) {
        dispatchAppUpdateAvailable({ buildId, source: "header" });
    }

    return response;
});
