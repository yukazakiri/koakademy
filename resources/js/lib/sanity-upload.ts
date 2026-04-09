import axios from "axios";
import { toast } from "sonner";
import { route } from "ziggy-js";

export interface UploadedImage {
    url: string;
    assetId: string;
    filename: string;
    alt: string;
}

export async function uploadImageToSanity(file: File): Promise<UploadedImage | null> {
    const formData = new FormData();
    formData.append("image", file);

    try {
        // Get CSRF token from cookie or meta tag
        const csrfToken =
            document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ||
            document.cookie
                .split("; ")
                .find((row) => row.startsWith("XSRF-TOKEN="))
                ?.split("=")[1];

        const response = await axios.post(route("administrators.sanity-content.upload-image"), formData, {
            headers: {
                "Content-Type": "multipart/form-data",
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
                ...(csrfToken ? { "X-XSRF-TOKEN": decodeURIComponent(csrfToken) } : {}),
            },
            withCredentials: true,
        });

        if (response.data.success && response.data.data) {
            return {
                url: response.data.data.url,
                assetId: response.data.data.assetId,
                filename: response.data.data.filename,
                alt: response.data.data.alt || generateAltFromFilename(file.name),
            };
        }

        // Fallback: check for flash data in redirect response
        if (response.data.props?.flash?.imageData) {
            const data = response.data.props.flash.imageData;
            return {
                url: data.url,
                assetId: data.assetId,
                filename: data.filename,
                alt: data.alt || generateAltFromFilename(file.name),
            };
        }

        console.error("Upload response missing data:", response.data);
        return null;
    } catch (error: any) {
        console.error("Upload error:", error);

        if (error.response?.data?.error) {
            toast.error(error.response.data.error);
        } else if (error.response?.data?.errors?.image) {
            toast.error(error.response.data.errors.image[0] || "Image validation failed");
        } else if (error.response?.data?.message) {
            toast.error(error.response.data.message);
        }

        return null;
    }
}

/**
 * Generate alt text from filename (fallback for frontend)
 */
function generateAltFromFilename(filename: string): string {
    // Remove file extension
    const name = filename.replace(/\.[^/.]+$/, "");

    // Replace underscores and hyphens with spaces
    const cleaned = name.replace(/[-_]+/g, " ");

    // Capitalize first letter of each word
    const capitalized = cleaned
        .toLowerCase()
        .split(" ")
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(" ")
        .trim();

    return capitalized || "Uploaded image";
}
