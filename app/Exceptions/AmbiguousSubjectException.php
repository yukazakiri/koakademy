<?php

declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a subject code matches more than one subject.
 *
 * A subject code is unique inside the program it belongs to, and every
 * program of a school repeats the generic codes of its curriculum. Rather
 * than guessing which program a request means, the tools report the
 * ambiguity and ask for the program.
 */
final class AmbiguousSubjectException extends InvalidArgumentException
{
    /**
     * The code matches subjects of more than one program.
     */
    public static function forCode(string $code): self
    {
        return new self(sprintf(
            'Subject code %s exists in more than one program. Pass course_id or course_code to identify the program.',
            $code,
        ));
    }

    /**
     * The code matches subjects of more than one program, and the caller can
     * only name the program.
     */
    public static function forClass(string $code): self
    {
        return new self(sprintf(
            'Subject code %s exists in more than one program. Pass a program to identify the subject.',
            $code,
        ));
    }
}
