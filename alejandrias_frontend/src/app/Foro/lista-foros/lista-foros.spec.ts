import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ListaForos } from './lista-foros';

describe('ListaForos', () => {
  let component: ListaForos;
  let fixture: ComponentFixture<ListaForos>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ListaForos],
    }).compileComponents();

    fixture = TestBed.createComponent(ListaForos);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
