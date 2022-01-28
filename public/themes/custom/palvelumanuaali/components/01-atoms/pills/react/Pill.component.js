/**
 * @file Pill.component.js
 * Exports a pill component.
 */

import React from 'react';
import PropTypes from 'prop-types';

/**
 * Component that renders a pill with a click handler.
 */
const Button = (props) => {
  const { onClick, children } = props;

  return (
    <div className="pill">
      {children}
    </div>
  );
};

Button.propTypes = {
  children: PropTypes.node,
};

Button.defaultProps = {
  children: null,
};

export default Pill;
