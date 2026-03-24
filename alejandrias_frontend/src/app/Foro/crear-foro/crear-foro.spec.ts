import { ComponentFixture, TestBed } from '@angular/core/testing';

import { CrearForo } from './crear-foro';

describe('CrearForo', () => {
  let component: CrearForo;
  let fixture: ComponentFixture<CrearForo>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [CrearForo],
    }).compileComponents();

    fixture = TestBed.createComponent(CrearForo);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
