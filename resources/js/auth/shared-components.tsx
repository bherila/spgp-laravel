import type { AuthComponentInput } from 'bwh-auth';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export { getCsrfToken } from '@/lib/apiClient';

export function getAuthComponents() {
  return {
    Button,
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
    Input,
    Label,
  } satisfies AuthComponentInput;
}
