import React from 'react';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { Pencil, Archive, RotateCcw, Users, Tag } from 'lucide-react';
import type { Season } from './types';
import { formatDate } from './utils';

interface SeasonTableProps {
  seasons: Season[];
  loading: boolean;
  onEdit: (season: Season) => void;
  onArchive: (season: Season) => void;
  onRestore: (season: Season) => void;
  onManagePassTypes: (season: Season) => void;
}

export function SeasonTable({
  seasons,
  loading,
  onEdit,
  onArchive,
  onRestore,
  onManagePassTypes
}: SeasonTableProps) {
  if (loading) {
    return (
      <div className="border rounded-lg">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Name</TableHead>
              <TableHead>Year</TableHead>
              <TableHead>Start Date</TableHead>
              <TableHead>Early Spring Deadline</TableHead>
              <TableHead>Final Deadline</TableHead>
              <TableHead>Requests</TableHead>
              <TableHead>Status</TableHead>
              <TableHead className="text-right">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {[...Array(5)].map((_, i) => (
              <TableRow key={i}>
                <TableCell><Skeleton className="h-5 w-32" /></TableCell>
                <TableCell><Skeleton className="h-5 w-12" /></TableCell>
                <TableCell><Skeleton className="h-5 w-24" /></TableCell>
                <TableCell><Skeleton className="h-5 w-24" /></TableCell>
                <TableCell><Skeleton className="h-5 w-24" /></TableCell>
                <TableCell><Skeleton className="h-5 w-8" /></TableCell>
                <TableCell><Skeleton className="h-6 w-16 rounded-full" /></TableCell>
                <TableCell className="text-right"><Skeleton className="h-8 w-24 ml-auto" /></TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>
    );
  }

  return (
    <div className="border rounded-lg">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Name</TableHead>
            <TableHead>Year</TableHead>
            <TableHead>Start Date</TableHead>
            <TableHead>Early Spring Deadline</TableHead>
            <TableHead>Final Deadline</TableHead>
            <TableHead>Requests</TableHead>
            <TableHead>Status</TableHead>
            <TableHead className="text-right">Actions</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {seasons.length === 0 ? (
            <TableRow>
              <TableCell colSpan={8} className="text-center py-8 text-muted-foreground">
                No seasons found.
              </TableCell>
            </TableRow>
          ) : (
            seasons.map((season) => (
              <TableRow key={season.id} className={season.deleted_at ? 'opacity-60' : ''}>
                <TableCell className="font-medium">{season.pass_name}</TableCell>
                <TableCell>{season.pass_year}</TableCell>
                <TableCell>{formatDate(season.start_date)}</TableCell>
                <TableCell>{formatDate(season.early_spring_deadline)}</TableCell>
                <TableCell>{formatDate(season.final_deadline)}</TableCell>
                <TableCell>
                  <a 
                    href={`/admin/seasons/${season.id}/pass-requests`}
                    className="text-primary hover:underline"
                  >
                    {season.pass_request_count}
                  </a>
                </TableCell>
                <TableCell>
                  {season.deleted_at ? (
                    <Badge variant="secondary">Archived</Badge>
                  ) : new Date(season.final_deadline) < new Date() ? (
                    <Badge variant="destructive">Ended</Badge>
                  ) : (
                    <Badge variant="default">Active</Badge>
                  )}
                </TableCell>
                <TableCell className="text-right">
                  <div className="flex items-center justify-end gap-2">
                    {season.deleted_at ? (
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => onRestore(season)}
                      >
                        <RotateCcw className="w-4 h-4 mr-1" />
                        Restore
                      </Button>
                    ) : (
                      <>
                        <Button
                          variant="outline"
                          size="sm"
                          asChild
                        >
                          <a href={`/admin/seasons/${season.id}/pass-requests`}>
                            <Users className="w-4 h-4" />
                          </a>
                        </Button>
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => onManagePassTypes(season)}
                          title="Manage Pass Types"
                        >
                          <Tag className="w-4 h-4" />
                        </Button>
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => onEdit(season)}
                        >
                          <Pencil className="w-4 h-4" />
                        </Button>
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => onArchive(season)}
                        >
                          <Archive className="w-4 h-4" />
                        </Button>
                      </>
                    )}
                  </div>
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>
    </div>
  );
}
