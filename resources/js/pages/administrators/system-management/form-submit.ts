import { toast } from "sonner";

interface SubmitForm<TForm = any> {
    put: (url: string, options: { onSuccess: () => void; onError: () => void }) => void;
    post: (
        url: string,
        options: {
            forceFormData?: boolean;
            onSuccess: () => void;
            onError: () => void;
        },
    ) => void;
    transform: (callback: (data: TForm) => TForm) => void;
}

interface SubmitSystemFormOptions {
    form: SubmitForm;
    routeName: string;
    successMessage: string;
    errorMessage: string;
    hasFiles?: boolean;
}

export function submitSystemForm({ form, routeName, successMessage, errorMessage, hasFiles = false }: SubmitSystemFormOptions): void {
    if (hasFiles) {
        form.transform((data: any) => ({
            ...data,
            _method: "PUT",
        }));
        
        form.post(route(routeName), {
            forceFormData: true,
            onSuccess: () => toast.success(successMessage),
            onError: () => toast.error(errorMessage),
        });

        return;
    }

    form.put(route(routeName), {
        onSuccess: () => toast.success(successMessage),
        onError: () => toast.error(errorMessage),
    });
}
