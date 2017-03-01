import os
import math

import numpy as np
import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt

from sklearn.learning_curve import learning_curve

class LearningCurve(object):

    def __init__(self, dirname):
        self.dirname = dirname

    def set_classifier(self, classifier):
        self.classifier = classifier

    def save(self, X, y, figure_id=1):

        plt.figure(figure_id)
        plt.xlabel("Training samples")
        plt.ylabel("Error")

        train_sizes, train_scores, test_scores = learning_curve(self.classifier, X, y[:,0])

        train_error_mean = 1 - np.mean(train_scores, axis=1)
        train_scores_std = np.std(train_scores, axis=1)
        test_error_mean = 1 - np.mean(test_scores, axis=1)
        test_scores_std = np.std(test_scores, axis=1)
        plt.grid()

        plt.fill_between(train_sizes, train_error_mean + train_scores_std,
                         train_error_mean - train_scores_std, alpha=0.1,
                         color="r")
        plt.fill_between(train_sizes, test_error_mean + test_scores_std,
                         test_error_mean - test_scores_std, alpha=0.1, color="g")
        plt.plot(train_sizes, train_error_mean, 'o-', color="r",
                 label="Training error")
        plt.plot(train_sizes, test_error_mean, 'o-', color="g",
                 label="Cross-validation error")
        plt.legend(loc="best")

        filepath = os.path.join(self.dirname, 'learning-curve.png')
        plt.savefig(filepath, format='png')

        if not os.path.isfile(filepath):
            return False

        return filepath

