import PortalLayout from "@/components/portal-layout";
import { User } from "@/types/user";

interface FacultyLayoutProps {
    user: User;
    children: React.ReactNode;
}

export default function FacultyLayout({ user, children }: FacultyLayoutProps) {
    return <PortalLayout user={user}>{children}</PortalLayout>;
}
