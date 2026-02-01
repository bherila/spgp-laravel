import '../bootstrap';
import { createRoot } from 'react-dom/client';
import React, { useEffect, useState } from 'react';
import MainTitle from '@/components/MainTitle';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Skeleton } from '@/components/ui/skeleton';
import { ChevronLeft, ChevronRight } from 'lucide-react';

interface SeasonPassType {
  id: number;
  season_id: number;
  pass_type_name: string;
  regular_early_price: string;
  regular_regular_price: string;
  renewal_early_price: string;
  renewal_regular_price: string;
  group_early_price: string;
  group_regular_price: string;
  sort_order: number;
}

interface Season {
  id: number;
  pass_name: string;
  pass_year: number;
  start_date: string;
  early_spring_deadline: string;
  final_deadline: string;
  pass_types: SeasonPassType[];
}

type Step = 'type' | 'details';

function formatDate(dateStr: string | null): string {
  if (!dateStr) return '—';
  const date = new Date(dateStr);
  return date.toLocaleDateString(undefined, { 
    year: 'numeric', 
    month: 'long', 
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

function PassRequestForm() {
  const mount = document.getElementById('request');
  const csrfToken = mount?.getAttribute('data-csrf-token') || '';
  const initialSeasonId = mount?.getAttribute('data-season-id') ? Number(mount.getAttribute('data-season-id')) : null;

  const [step, setStep] = useState<Step>('type');
  const [seasons, setSeasons] = useState<Season[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [success, setSuccess] = useState(false);

  // Form state
  const [seasonId, setSeasonId] = useState<number | null>(initialSeasonId);
  const [passTypeId, setPassTypeId] = useState<number | null>(null);
  const [isRenewal, setIsRenewal] = useState(false);
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [email, setEmail] = useState('');
  const [dateOfBirth, setDateOfBirth] = useState('');
  const [renewalPassId, setRenewalPassId] = useState('');

  useEffect(() => {
    const fetchSeasons = async () => {
      try {
        setLoading(true);
        // If we have an initialSeasonId, we might still want to fetch it to get its details
        const response = await fetch('/api/pass-requests/seasons', {
          headers: { 'Accept': 'application/json' },
        });
        if (!response.ok) throw new Error('Failed to fetch seasons');
        const data = await response.json();
        setSeasons(data);
        
        if (!seasonId && data.length > 0) {
          setSeasonId(data[0].id);
        }
      } catch (err) {
        setError(err instanceof Error ? err.message : 'An error occurred');
      } finally {
        setLoading(false);
      }
    };

    fetchSeasons();
  }, []);

  // Reset pass type when season changes
  useEffect(() => {
    setPassTypeId(null);
  }, [seasonId]);

  const selectedSeason = seasons.find(s => s.id === seasonId);
  const selectedPassType = selectedSeason?.pass_types.find(pt => pt.id === passTypeId);
  const availablePassTypes = selectedSeason?.pass_types || [];

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (submitting || !passTypeId) return;

    setError(null);
    setSubmitting(true);

    try {
      const response = await fetch('/api/pass-requests', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({
          season_id: seasonId,
          season_pass_type_id: passTypeId,
          is_renewal: isRenewal,
          passholder_first_name: firstName,
          passholder_last_name: lastName,
          passholder_email: email,
          passholder_birth_date: dateOfBirth,
          renewal_pass_id: isRenewal ? renewalPassId : null,
        }),
      });

      if (!response.ok) {
        const data = await response.json();
        throw new Error(data.message || 'Failed to submit pass request');
      }

      setSuccess(true);
      // Redirect to dashboard after success
      setTimeout(() => {
        window.location.href = '/dashboard';
      }, 2000);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An error occurred');
    } finally {
      setSubmitting(false);
    }
  };

  const canProceedToDetails = seasonId && passTypeId;

  // Format price for display
  const formatPrice = (price: string) => {
    const num = parseFloat(price);
    return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(num);
  };

  // Check if we're in early bird period
  const isEarlyBird = selectedSeason && new Date() < new Date(selectedSeason.early_spring_deadline);

  if (success) {
    return (
      <div className="max-w-2xl mx-auto px-4 py-8">
        <Card>
          <CardContent className="pt-6">
            <Alert>
              <AlertDescription className="text-green-600">
                Pass request submitted successfully! Redirecting to dashboard...
              </AlertDescription>
            </Alert>
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="max-w-2xl mx-auto px-4 py-8">
      <div className="mb-8">
        <MainTitle>New Pass Request</MainTitle>
        <p className="text-muted-foreground mt-2">
          Request a new ski pass for yourself or a family member.
        </p>
      </div>

      {loading ? (
        <Card>
          <CardHeader>
            <Skeleton className="h-6 w-40" />
            <Skeleton className="h-4 w-60 mt-2" />
          </CardHeader>
          <CardContent className="space-y-4">
            <Skeleton className="h-10 w-full" />
            <Skeleton className="h-10 w-full" />
            <Skeleton className="h-10 w-full" />
          </CardContent>
        </Card>
      ) : seasons.length === 0 ? (
        <Card>
          <CardContent className="pt-6">
            <Alert>
              <AlertDescription>
                No active seasons available for pass requests at this time.
              </AlertDescription>
            </Alert>
          </CardContent>
        </Card>
      ) : step === 'type' ? (
        <Card>
          <CardHeader>
            <CardTitle>Step 1: Select Pass Type</CardTitle>
            <CardDescription>
              Choose the season and type of pass you want to request.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="space-y-6">
              <div className="space-y-1">
                <Label>Season</Label>
                <p className="text-lg font-semibold">
                  {selectedSeason?.pass_name} {selectedSeason?.pass_year}
                </p>
              </div>

              <div className="space-y-2">
                <Label>Pass Type</Label>
                {availablePassTypes.length === 0 ? (
                  <Alert>
                    <AlertDescription>
                      No pass types available for this season. Please contact an administrator.
                    </AlertDescription>
                  </Alert>
                ) : (
                  <div className="grid gap-2">
                    {availablePassTypes.map((type) => {
                      const groupPrice = isEarlyBird ? type.group_early_price : type.group_regular_price;
                      
                      // Determine regular price based on renewal status and early bird
                      let regularPrice;
                      if (isRenewal) {
                        regularPrice = isEarlyBird ? type.renewal_early_price : type.renewal_regular_price;
                      } else {
                        regularPrice = isEarlyBird ? type.regular_early_price : type.regular_regular_price;
                      }
                      
                      const savings = parseFloat(regularPrice) - parseFloat(groupPrice);
                      return (
                        <label
                          key={type.id}
                          className={`flex items-center justify-between p-3 rounded-md border cursor-pointer transition-colors ${
                            passTypeId === type.id
                              ? 'border-primary bg-primary/5'
                              : 'border-input hover:bg-muted'
                          }`}
                        >
                          <div className="flex items-center">
                            <input
                              type="radio"
                              name="passType"
                              value={type.id}
                              checked={passTypeId === type.id}
                              onChange={() => setPassTypeId(type.id)}
                              className="mr-3"
                            />
                            <span>{type.pass_type_name}</span>
                          </div>
                          <div className="text-right text-sm">
                            <span className="font-medium">{formatPrice(groupPrice)}</span>
                            <span className="text-muted-foreground line-through ml-2">{formatPrice(regularPrice)}</span>
                            {savings > 0 && (
                              <span className="text-green-600 ml-2">(Save {formatPrice(savings.toString())})</span>
                            )}
                          </div>
                        </label>
                      );
                    })}
                  </div>
                )}
                {isEarlyBird && selectedSeason && (
                  <p className="text-sm text-green-600">
                    🎉 Early bird pricing! Valid until {formatDate(selectedSeason.early_spring_deadline)}
                  </p>
                )}
              </div>

              <div className="flex items-center gap-2">
                <input
                  type="checkbox"
                  id="isRenewal"
                  checked={isRenewal}
                  onChange={(e) => setIsRenewal(e.target.checked)}
                  className="h-4 w-4 rounded border-gray-300"
                />
                <Label htmlFor="isRenewal">
                  This is a renewal from a previous season
                </Label>
              </div>

              <div className="flex justify-end">
                <Button
                  type="button"
                  onClick={() => setStep('details')}
                  disabled={!canProceedToDetails}
                >
                  Continue
                  <ChevronRight className="w-4 h-4 ml-2" />
                </Button>
              </div>
            </div>
          </CardContent>
        </Card>
      ) : (
        <form onSubmit={handleSubmit}>
          <Card>
            <CardHeader>
              <CardTitle>Step 2: Passholder Details</CardTitle>
              <CardDescription>
                Enter the details for the passholder.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div className="space-y-6">
                {/* Summary of step 1 */}
                <div className="bg-muted/50 rounded-lg p-4 flex justify-between items-center">
                  <div>
                    <p className="font-medium">{selectedPassType?.pass_type_name}</p>
                    <p className="text-sm text-muted-foreground">
                      {selectedSeason?.pass_name}{' '}
                      {selectedSeason?.pass_year}
                      {isRenewal && ' • Renewal'}
                    </p>
                  </div>
                  <Button type="button" variant="ghost" size="sm" onClick={() => setStep('type')}>
                    <ChevronLeft className="w-4 h-4 mr-1" />
                    Edit
                  </Button>
                </div>

                {isRenewal && (
                  <div className="space-y-2">
                    <Label htmlFor="renewalPassId">Renewal Pass ID *</Label>
                    <Input
                      id="renewalPassId"
                      value={renewalPassId}
                      onChange={(e) => setRenewalPassId(e.target.value)}
                      placeholder="I9..."
                      pattern="I9\d+"
                      required
                    />
                    <p className="text-sm text-muted-foreground">
                      The pass ID begins with I900 and must be entered exactly. No spaces or dashes.
                    </p>
                  </div>
                )}

                <div className="grid grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <Label htmlFor="firstName">First Name *</Label>
                    <Input
                      id="firstName"
                      value={firstName}
                      onChange={(e) => setFirstName(e.target.value)}
                      required
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="lastName">Last Name *</Label>
                    <Input
                      id="lastName"
                      value={lastName}
                      onChange={(e) => setLastName(e.target.value)}
                      required
                    />
                  </div>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="email">
                    Email associated with Ikon Pass Account *
                  </Label>
                  <Input
                    id="email"
                    type="email"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    required
                  />
                  <p className="text-sm text-muted-foreground">
                    This may be your own email or the individual's email. It does not need to be a Meta email.
                  </p>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="dateOfBirth">Date of Birth *</Label>
                  <Input
                    id="dateOfBirth"
                    type="date"
                    value={dateOfBirth}
                    onChange={(e) => setDateOfBirth(e.target.value)}
                    required
                  />
                </div>

                {error && (
                  <Alert variant="destructive">
                    <AlertDescription>{error}</AlertDescription>
                  </Alert>
                )}

                <div className="flex justify-between">
                  <Button type="button" variant="outline" onClick={() => setStep('type')}>
                    <ChevronLeft className="w-4 h-4 mr-2" />
                    Back
                  </Button>
                  <Button type="submit" disabled={submitting}>
                    {submitting ? 'Submitting...' : 'Submit Request'}
                  </Button>
                </div>
              </div>
            </CardContent>
          </Card>
        </form>
      )}
    </div>
  );
}

const requestElement = document.getElementById('request');
if (requestElement) {
  createRoot(requestElement).render(<PassRequestForm />);
}
