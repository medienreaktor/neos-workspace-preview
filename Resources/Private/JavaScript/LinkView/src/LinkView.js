import React, {PureComponent} from 'react';
import PropTypes from 'prop-types';
import Clipboard from 'react-clipboard.js';
import {dataLoader} from '@neos-project/neos-ui-views';
import {neos} from '@neos-project/neos-ui-decorators';
import {Icon, Button} from '@neos-project/react-ui-components';

@dataLoader()
@neos(globalRegistry => ({
  i18nRegistry: globalRegistry.get('i18n'),
}))
export default class LinkView extends PureComponent {
  static propTypes = {
    data: PropTypes.object.isRequired
  };

  render() {
    const linkUrl = this.props.data.link;
    const error = this.props.data.error;

    if (error !== 'none') {
      return (
        <div>{this.props.i18nRegistry.translate('Flownative.WorkspacePreview:Main:error.' + error, 'Error: ' + error)}</div>
      )
    }
    return (
      <Clipboard
          data-clipboard-text={linkUrl}
          component={Button}
          button-style="lighter"
      >
        <Icon icon="copy" padded="right"/>
        {this.props.i18nRegistry.translate('Flownative.WorkspacePreview:Main:copyPreviewLink', 'Copy Preview Link')}
      </Clipboard>
    );
  }
}
