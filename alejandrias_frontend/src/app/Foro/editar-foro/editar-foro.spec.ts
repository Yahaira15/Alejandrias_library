import { ComponentFixture, TestBed } from '@angular/core/testing';

import { EditarForo } from './editar-foro';

describe('EditarForo', () => {
  let component: EditarForo;
  let fixture: ComponentFixture<EditarForo>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [EditarForo],
    }).compileComponents();

    fixture = TestBed.createComponent(EditarForo);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
