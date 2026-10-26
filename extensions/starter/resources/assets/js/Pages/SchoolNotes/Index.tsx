import { store } from "@/actions/Koakademy/SchoolNotes/Http/Controllers/SchoolNoteController";
import AdminLayout from "@/components/administrators/admin-layout";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import type { User } from "@/types/user";
import { Form, Head } from "@inertiajs/react";

interface Props {
    user: User;
    school: { id: number; name: string };
    notes: { id: number; body: string; created_at: string }[];
    canCreate: boolean;
}

export default function SchoolNotes({ user, school, notes, canCreate }: Props) {
    return (
        <AdminLayout user={user}>
            <Head title="School notes" />
            <div className="flex flex-col gap-6">
                <div>
                    <h1 className="text-2xl font-semibold">School notes</h1>
                    <p className="text-muted-foreground text-sm">{school.name}</p>
                </div>
                {canCreate && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Add a note</CardTitle>
                            <CardDescription>Only staff with access to this school can view its notes.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form action={store()} resetOnSuccess className="flex flex-col gap-4">
                                {({ errors, processing }) => (
                                    <>
                                        <Label htmlFor="school-note-body">Note</Label>
                                        <Textarea
                                            id="school-note-body"
                                            name="body"
                                            required
                                            maxLength={2000}
                                            aria-describedby={errors.body ? "note-error" : undefined}
                                        />
                                        {errors.body && (
                                            <p id="note-error" role="alert" className="text-destructive text-sm">
                                                {errors.body}
                                            </p>
                                        )}
                                        <Button type="submit" disabled={processing} className="self-start">
                                            {processing ? "Saving…" : "Save note"}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                )}
                <section aria-label="Recent school notes" className="flex flex-col gap-4">
                    {notes.length === 0 ? (
                        <p className="text-muted-foreground">No notes have been added for this school.</p>
                    ) : (
                        notes.map((note) => (
                            <Card key={note.id}>
                                <CardContent className="pt-6">
                                    <p className="break-words whitespace-pre-wrap">{note.body}</p>
                                </CardContent>
                            </Card>
                        ))
                    )}
                </section>
            </div>
        </AdminLayout>
    );
}
