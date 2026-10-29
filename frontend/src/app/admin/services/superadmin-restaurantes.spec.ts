import { TestBed } from '@angular/core/testing';

import { SuperadminRestaurantes } from './superadmin-restaurantes';

describe('SuperadminRestaurantes', () => {
  let service: SuperadminRestaurantes;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(SuperadminRestaurantes);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
