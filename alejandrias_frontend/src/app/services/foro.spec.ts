import { TestBed } from '@angular/core/testing';

import { Foro } from './foro';

describe('Foro', () => {
  let service: Foro;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(Foro);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });
});
