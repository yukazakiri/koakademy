import PortalLayout from "@/components/portal-layout";
import { User } from "@/types/user";

interface StudentLayoutProps {
    user: User;
    children: React.ReactNode;
}

export default function StudentLayout({ user, children }: StudentLayoutProps) {
    return <PortalLayout user={user}>{children}</PortalLayout>;
}
