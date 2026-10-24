import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import type { ReactNode } from "react";

const PAGE_SIZES = [10, 20, 30, 40, 50];

interface DataTablePaginationProps {
    page: number;
    lastPage: number;
    perPage: number;
    total: number;
    from: number;
    to: number;
    isLoading?: boolean;
    onPageChange?: (page: number, perPage: number) => void;
    viewOptions?: ReactNode;
}

export function DataTablePagination({
    page,
    lastPage,
    perPage,
    total,
    from,
    to,
    isLoading = false,
    onPageChange,
    viewOptions,
}: DataTablePaginationProps) {
    const goTo = (nextPage: number, nextPerPage = perPage) => {
        if (isLoading) {
            return;
        }

        onPageChange?.(nextPage, nextPerPage);
    };

    return (
        <div className="flex flex-wrap items-center justify-between gap-4 border-t p-4 sm:px-6">
            <div className="text-muted-foreground text-sm">
                {total === 0 ? "0 classes" : `${from.toLocaleString()}–${to.toLocaleString()} of ${total.toLocaleString()} classes`}
            </div>
            <div className="flex flex-wrap items-center gap-4 sm:gap-6">
                {viewOptions}
                <div className="flex items-center gap-2">
                    <label htmlFor="classes-per-page" className="text-muted-foreground text-sm">
                        Per page
                    </label>
                    <Select
                        value={`${perPage}`}
                        disabled={isLoading}
                        onValueChange={(value) => {
                            goTo(1, Number(value));
                        }}
                    >
                        <SelectTrigger id="classes-per-page" className="h-9 w-20">
                            <SelectValue placeholder={perPage} />
                        </SelectTrigger>
                        <SelectContent side="top">
                            {PAGE_SIZES.map((pageSize) => (
                                <SelectItem key={pageSize} value={`${pageSize}`}>
                                    {pageSize}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <div className="flex items-center justify-center text-sm tabular-nums">
                    Page {page} of {Math.max(lastPage, 1)}
                </div>
                <div className="flex items-center gap-2">
                    <Button variant="outline" className="hidden h-8 w-8 p-0 lg:flex" onClick={() => goTo(1)} disabled={page <= 1 || isLoading}>
                        <span className="sr-only">Go to first page</span>
                        <ChevronsLeft className="h-4 w-4" />
                    </Button>
                    <Button variant="outline" className="h-8 w-8 p-0" onClick={() => goTo(page - 1)} disabled={page <= 1 || isLoading}>
                        <span className="sr-only">Go to previous page</span>
                        <ChevronLeft className="h-4 w-4" />
                    </Button>
                    <Button variant="outline" className="h-8 w-8 p-0" onClick={() => goTo(page + 1)} disabled={page >= lastPage || isLoading}>
                        <span className="sr-only">Go to next page</span>
                        <ChevronRight className="h-4 w-4" />
                    </Button>
                    <Button
                        variant="outline"
                        className="hidden h-8 w-8 p-0 lg:flex"
                        onClick={() => goTo(lastPage)}
                        disabled={page >= lastPage || isLoading}
                    >
                        <span className="sr-only">Go to last page</span>
                        <ChevronsRight className="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>
    );
}
