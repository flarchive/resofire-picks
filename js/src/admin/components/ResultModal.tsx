import app from 'flarum/admin/app';
import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import type Mithril from 'mithril';
import PickEvent from '../../common/models/PickEvent';
import Team from '../../common/models/Team';

interface ResultModalAttrs extends IInternalModalAttrs {
  event: PickEvent;
  onsave: () => void;
}

export default class ResultModal extends Modal<ResultModalAttrs> {
  private event!: PickEvent;
  private homeScore: string = '';
  private awayScore: string = '';
  private loading: boolean = false;

  oninit(vnode: Mithril.Vnode<ResultModalAttrs, this>) {
    super.oninit(vnode);
    this.event = this.attrs.event;
    this.homeScore = this.event.homeScore() !== null ? String(this.event.homeScore()) : '';
    this.awayScore = this.event.awayScore() !== null ? String(this.event.awayScore()) : '';
  }

  className() {
    return 'ResultModal Modal--small';
  }

  title() {
    return app.translator.trans('resofire-picks.admin.games.enter_result');
  }

  content() {
    const homeTeam = this.event.homeTeam() as Team | false;
    const awayTeam = this.event.awayTeam() as Team | false;

    const home = Number(this.homeScore);
    const away = Number(this.awayScore);
    let resultPreview = '';
    if (this.homeScore !== '' && this.awayScore !== '') {
      if (home > away) resultPreview = (homeTeam ? homeTeam.name() : 'Home') + ' wins';
      else if (away > home) resultPreview = (awayTeam ? awayTeam.name() : 'Away') + ' wins';
      else resultPreview = 'Tied — college football cannot end in a tie. Please check scores.';
    }

    return (
      <div className="Modal-body">
        <div className="Form">

          <div className="Form-group">
            <label>
              {homeTeam ? homeTeam.name() : 'Home Team'} (Home)
            </label>
            <input
              className="FormControl"
              type="number"
              min="0"
              placeholder="0"
              value={this.homeScore}
              oninput={(e: InputEvent) => { this.homeScore = (e.target as HTMLInputElement).value; }}
            />
          </div>

          <div className="Form-group">
            <label>
              {awayTeam ? awayTeam.name() : 'Away Team'} (Away)
            </label>
            <input
              className="FormControl"
              type="number"
              min="0"
              placeholder="0"
              value={this.awayScore}
              oninput={(e: InputEvent) => { this.awayScore = (e.target as HTMLInputElement).value; }}
            />
          </div>

          {resultPreview && (
            <div className="Form-group">
              <p className="PicksResultPreview">
                <strong>{app.translator.trans('resofire-picks.admin.games.result_preview')}:</strong>{' '}
                {resultPreview}
              </p>
            </div>
          )}

          <div className="Form-group">
            <Button
              className="Button Button--primary"
              type="submit"
              loading={this.loading}
            >
              {app.translator.trans('resofire-picks.admin.common.save')}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  async onsubmit(e: SubmitEvent) {
    e.preventDefault();

    if (this.homeScore === '' || this.awayScore === '') {
      return;
    }

    this.loading = true;
    m.redraw();

    try {
      await app.request({
        method: 'POST',
        url: `${app.forum.attribute('apiUrl')}/picks/events/${this.event.id()}/result`,
        body: {
          homeScore: parseInt(this.homeScore),
          awayScore: parseInt(this.awayScore),
        },
      });

      this.attrs.onsave();
      this.hide();
    } catch (error: any) {
      this.loading = false;
      this.alertAttrs = error.alert;
      m.redraw();
    }
  }
}
