import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, RouterModule } from '@angular/router';
import { ViewportScroller } from '@angular/common';

@Component({
  selector: 'app-legal',
  imports: [RouterModule],
  templateUrl: './legal.html',
  styleUrl: './legal.scss'
})
export class Legal implements OnInit {
  constructor(
    private route: ActivatedRoute,
    private viewportScroller: ViewportScroller
  ) {}

  ngOnInit(): void {
    this.route.fragment.subscribe(fragment => {
      if (!fragment) return;

      setTimeout(() => this.viewportScroller.scrollToAnchor(fragment), 0);
    });
  }
}
