import { getAgeGroup } from '@/lib/passRequestHelpers';

describe('getAgeGroup', () => {
  function birthDateForAge(age: number): string {
    const d = new Date();
    d.setFullYear(d.getFullYear() - age);
    return d.toISOString().slice(0, 10);
  }

  it('returns empty string for null', () => {
    expect(getAgeGroup(null)).toBe('');
  });

  it('returns "4 & under" for age 0', () => {
    expect(getAgeGroup(birthDateForAge(0))).toBe('4 & under');
  });

  it('returns "4 & under" for age 4', () => {
    expect(getAgeGroup(birthDateForAge(4))).toBe('4 & under');
  });

  it('returns "Child (5-12)" for age 5', () => {
    expect(getAgeGroup(birthDateForAge(5))).toBe('Child (5-12)');
  });

  it('returns "Child (5-12)" for age 12', () => {
    expect(getAgeGroup(birthDateForAge(12))).toBe('Child (5-12)');
  });

  it('returns "Young Adult (13-22)" for age 13', () => {
    expect(getAgeGroup(birthDateForAge(13))).toBe('Young Adult (13-22)');
  });

  it('returns "Young Adult (13-22)" for age 22', () => {
    expect(getAgeGroup(birthDateForAge(22))).toBe('Young Adult (13-22)');
  });

  it('returns "Adult (23+)" for age 23', () => {
    expect(getAgeGroup(birthDateForAge(23))).toBe('Adult (23+)');
  });

  it('returns "Adult (23+)" for age 50', () => {
    expect(getAgeGroup(birthDateForAge(50))).toBe('Adult (23+)');
  });
});
