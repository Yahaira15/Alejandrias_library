import { Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { FormsModule } from '@angular/forms';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [
    RouterOutlet,
    FormsModule  
  ],
  templateUrl: './app.html',
  styleUrls: ['./app.scss']
})
export class App {
  
}