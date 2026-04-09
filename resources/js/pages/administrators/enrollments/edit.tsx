import { ComponentProps } from "react";
import AdministratorEnrollmentCreate from "./create";

type AdministratorEnrollmentCreateProps = ComponentProps<typeof AdministratorEnrollmentCreate>;

export default function AdministratorEnrollmentEdit(props: AdministratorEnrollmentCreateProps) {
    return <AdministratorEnrollmentCreate {...props} />;
}
