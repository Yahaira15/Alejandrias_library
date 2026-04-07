import { Component } from '@angular/core';
import { Router } from '@angular/router';

@Component({
  selector: 'app-home',
  imports: [],
  templateUrl: './home.html',
  styleUrl: './home.scss',
})
export class Home {
  constructor(private router: Router) {}

  irARegistro(rol: string) {
  this.router.navigate(['/register'], { queryParams: { rol } });
  }

  irALogin() {
    this.router.navigate(['/login'])
  }
}
